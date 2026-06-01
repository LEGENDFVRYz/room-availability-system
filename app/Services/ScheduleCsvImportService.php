<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Room;
use App\Models\Schedule;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ScheduleCsvImportService
{
    private const EXPECTED_HEADERS = [
        'day',
        'room_code',
        'start_time',
        'end_time',
        'subject_code',
        'subject_title',
        'section',
        'instructor_name',
    ];

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function import(
        UploadedFile $file,
        int $yearStart,
        int $semester,
        bool $replaceExisting,
        ?int $userId = null,
    ): array {
        $term = AcademicTerm::query()
            ->where('year_start', $yearStart)
            ->where('semester', $semester)
            ->first();

        if (! $term) {
            throw ValidationException::withMessages([
                'year_start' => 'The selected academic term and semester do not exist yet. Create or activate the term first in Manage Config.',
            ]);
        }

        [$rows, $rejectedRows] = $this->parseCsv($file);

        if ($rows === [] && $rejectedRows === []) {
            throw ValidationException::withMessages([
                'csv_file' => 'The CSV has no schedule rows to import.',
            ]);
        }

        $roomCodes = collect($rows)->pluck('room_code')->unique()->values();
        $rooms = Room::query()
            ->whereIn('code', $roomCodes)
            ->get()
            ->keyBy(fn (Room $room) => strtoupper(trim($room->code)));

        foreach ($rows as $key => $row) {
            if (! $rooms->has($row['room_code'])) {
                $rejectedRows[] = $this->rejectedRow($row, 'Room code was not found. Add the room first or correct the room_code value.');
                unset($rows[$key]);
            }
        }

        $rows = array_values($rows);

        $internalConflictMap = $this->findInternalConflicts($rows);

        foreach ($rows as $key => $row) {
            if (! isset($internalConflictMap[$row['row_number']])) {
                continue;
            }

            $rejectedRows[] = $this->rejectedRow(
                $row,
                'Overlaps another row in this CSV upload.',
                implode(', ', $internalConflictMap[$row['row_number']]),
            );

            unset($rows[$key]);
        }

        $rows = array_values($rows);

        if (! $replaceExisting) {
            $existingSchedules = Schedule::query()
                ->with('room:id,code')
                ->where('academic_term_id', $term->id)
                ->where('is_active', true)
                ->get();

            foreach ($rows as $key => $row) {
                $room = $rooms->get($row['room_code']);

                $conflictingExisting = $existingSchedules->first(function (Schedule $schedule) use ($row, $room) {
                    return (int) $schedule->room_id === (int) $room->id
                        && (int) $schedule->day_of_week->value === (int) $row['day_of_week']
                        && $this->timesOverlap(
                            $row['start_time'],
                            $row['end_time'],
                            (string) $schedule->start_time,
                            (string) $schedule->end_time,
                        );
                });

                if (! $conflictingExisting) {
                    continue;
                }

                $rejectedRows[] = $this->rejectedRow(
                    $row,
                    'Overlaps an existing active schedule for the selected academic term.',
                    sprintf(
                        'Existing schedule #%s: %s %s %s-%s',
                        $conflictingExisting->id,
                        $conflictingExisting->subject_code,
                        $conflictingExisting->section,
                        $this->displayTime((string) $conflictingExisting->start_time),
                        $this->displayTime((string) $conflictingExisting->end_time),
                    ),
                );

                unset($rows[$key]);
            }
        }

        $rows = array_values($rows);

        DB::transaction(function () use ($term, $rooms, $rows, $replaceExisting, $userId) {
            if ($replaceExisting) {
                Schedule::query()
                    ->where('academic_term_id', $term->id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'updated_by' => $userId,
                    ]);
            }

            foreach ($rows as $row) {
                $room = $rooms->get($row['room_code']);

                Schedule::query()->updateOrCreate(
                    [
                        'academic_term_id' => $term->id,
                        'room_id' => $room->id,
                        'subject_code' => $row['subject_code'],
                        'section' => $row['section'],
                        'day_of_week' => $row['day_of_week'],
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'],
                    ],
                    [
                        'subject_title' => $row['subject_title'],
                        'instructor_name' => $row['instructor_name'],
                        'is_active' => true,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ],
                );
            }
        });

        return [
            'academic_term' => $term->label,
            'replace_existing' => $replaceExisting,
            'total_rows' => count($rows) + count($rejectedRows),
            'imported_count' => count($rows),
            'skipped_count' => count($rejectedRows),
            'rejected_rows' => array_values($rejectedRows),
        ];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     *
     * @throws ValidationException
     */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if (! $handle) {
            throw ValidationException::withMessages([
                'csv_file' => 'The uploaded CSV could not be opened.',
            ]);
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                throw ValidationException::withMessages([
                    'csv_file' => 'The CSV file is empty.',
                ]);
            }

            $normalizedHeaders = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);

            if ($normalizedHeaders !== self::EXPECTED_HEADERS) {
                throw ValidationException::withMessages([
                    'csv_file' => 'Invalid CSV headers. Use exactly: ' . implode(', ', self::EXPECTED_HEADERS) . '.',
                ]);
            }

            $rows = [];
            $rejectedRows = [];
            $lineNumber = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($this->isBlankCsvRow($data)) {
                    continue;
                }

                if (count($data) !== count(self::EXPECTED_HEADERS)) {
                    $rejectedRows[] = [
                        'row_number' => $lineNumber,
                        'reason' => 'Invalid column count. Expected ' . count(self::EXPECTED_HEADERS) . ' columns but found ' . count($data) . '.',
                        'conflicts_with' => '',
                    ];
                    continue;
                }

                $record = array_combine(
                    self::EXPECTED_HEADERS,
                    array_map(fn ($value) => trim((string) $value), $data),
                );

                if (! is_array($record)) {
                    $rejectedRows[] = [
                        'row_number' => $lineNumber,
                        'reason' => 'The CSV row could not be read.',
                        'conflicts_with' => '',
                    ];
                    continue;
                }

                $record['row_number'] = $lineNumber;

                try {
                    $rows[] = $this->normalizeRow($record);
                } catch (RuntimeException $exception) {
                    $rejectedRows[] = $this->rejectedRow($record, $exception->getMessage());
                }
            }
        } finally {
            fclose($handle);
        }

        return [$rows, $rejectedRows];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        foreach (self::EXPECTED_HEADERS as $header) {
            if ($header === 'instructor_name') {
                continue;
            }

            if (trim((string) ($row[$header] ?? '')) === '') {
                throw new RuntimeException("Missing required value: {$header}.");
            }
        }

        $dayOfWeek = $this->dayOfWeek((string) $row['day']);
        $startTime = $this->normalizeTime((string) $row['start_time']);
        $endTime = $this->normalizeTime((string) $row['end_time']);

        if ($startTime >= $endTime) {
            throw new RuntimeException('end_time must be after start_time.');
        }

        return [
            'row_number' => (int) $row['row_number'],
            'day' => $this->displayDay($dayOfWeek),
            'day_of_week' => $dayOfWeek,
            'room_code' => strtoupper(trim((string) $row['room_code'])),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'subject_code' => trim((string) $row['subject_code']),
            'subject_title' => $this->normalizeSubjectTitle((string) $row['subject_title']),
            'section' => trim((string) $row['section']),
            'instructor_name' => $this->normalizeInstructorName((string) ($row['instructor_name'] ?? '')),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<int, int>>
     */
    private function findInternalConflicts(array $rows): array
    {
        $conflicts = [];

        for ($i = 0; $i < count($rows); $i++) {
            for ($j = $i + 1; $j < count($rows); $j++) {
                $first = $rows[$i];
                $second = $rows[$j];

                if ($first['room_code'] !== $second['room_code']) {
                    continue;
                }

                if ((int) $first['day_of_week'] !== (int) $second['day_of_week']) {
                    continue;
                }

                if (! $this->timesOverlap($first['start_time'], $first['end_time'], $second['start_time'], $second['end_time'])) {
                    continue;
                }

                $conflicts[$first['row_number']][] = $second['row_number'];
                $conflicts[$second['row_number']][] = $first['row_number'];
            }
        }

        return array_map(fn ($values) => array_values(array_unique($values)), $conflicts);
    }

    private function timesOverlap(string $firstStart, string $firstEnd, string $secondStart, string $secondEnd): bool
    {
        return $firstStart < $secondEnd && $secondStart < $firstEnd;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header));
    }

    /**
     * @param array<int, string|null|false> $row
     */
    private function isBlankCsvRow(array $row): bool
    {
        return trim(implode('', array_map(fn ($value) => (string) $value, $row))) === '';
    }

    private function dayOfWeek(string $day): int
    {
        return match (strtolower(trim($day))) {
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
            default => throw new RuntimeException('Invalid day value. Use Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, or Sunday.'),
        };
    }

    private function displayDay(int $day): string
    {
        return match ($day) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => 'Unknown',
        };
    }

    private function normalizeTime(string $time): string
    {
        $time = strtoupper(trim($time));

        foreach (['H:i:s', 'H:i', 'G:i:s', 'G:i', 'g:i A', 'g:iA', 'h:i A', 'h:iA'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $time);

            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->format('H:i:s');
            }
        }

        throw new RuntimeException("Invalid time value: {$time}.");
    }

    private function displayTime(string $time): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!H:i:s', $time)
            ?: DateTimeImmutable::createFromFormat('!H:i', $time);

        return $parsed instanceof DateTimeImmutable ? $parsed->format('g:i A') : $time;
    }

    private function normalizeSubjectTitle(string $subjectTitle): string
    {
        $normalized = preg_replace('/\s+/', ' ', strtoupper(trim($subjectTitle))) ?: '';

        return match ($normalized) {
            'MICROPRO' => 'Microprocessors',
            'COMP PROG',
            'COM PROG' => 'Computer Programming',
            'TECHNO 101' => 'Technopreneurship 101',
            'COMP ARCHI' => 'Computer Architecture',
            default => trim($subjectTitle),
        };
    }

    private function normalizeInstructorName(string $instructorName): string
    {
        $name = trim($instructorName);

        if ($name === '' || strtoupper($name) === 'TBA') {
            return 'TBA';
        }

        if (str_starts_with($name, 'Engr. ')) {
            return $name;
        }

        $surname = trim(str_contains($name, '.') ? substr(strrchr($name, '.'), 1) : $name);
        $surname = preg_replace('/[^A-Za-z\- ]+/', '', $surname) ?: $name;

        $formattedSurname = match (strtoupper(str_replace(' ', '', $surname))) {
            'DELACRUZ' => 'Dela Cruz',
            default => implode(' ', array_map(
                fn ($part) => ucfirst(strtolower($part)),
                preg_split('/\s+/', trim($surname)) ?: [],
            )),
        };

        return 'Engr. ' . $formattedSurname;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function rejectedRow(array $row, string $reason, string $conflictsWith = ''): array
    {
        return [
            'row_number' => $row['row_number'] ?? '',
            'day' => $row['day'] ?? '',
            'room_code' => $row['room_code'] ?? '',
            'start_time' => $row['start_time'] ?? '',
            'end_time' => $row['end_time'] ?? '',
            'subject_code' => $row['subject_code'] ?? '',
            'subject_title' => $row['subject_title'] ?? '',
            'section' => $row['section'] ?? '',
            'instructor_name' => $row['instructor_name'] ?? '',
            'reason' => $reason,
            'conflicts_with' => $conflictsWith,
        ];
    }
}
