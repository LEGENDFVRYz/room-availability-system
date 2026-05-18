<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Enums\RoomType;
use App\Enums\Semester;
use App\Models\AcademicTerm;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;
use SplFileObject;

class ScheduleSeeder extends Seeder
{
    private const CSV_PATH = 'seeders/data/cpe_schedules_2025_second_semester.csv';

    public function run(): void
    {
        $csvPath = database_path(self::CSV_PATH);

        if (! is_file($csvPath)) {
            throw new RuntimeException("Schedule CSV not found at: {$csvPath}");
        }

        $term = AcademicTerm::query()
            ->where('year_start', 2025)
            ->where('semester', Semester::Second->value)
            ->firstOrFail();

        $adminId = User::query()->where('email', 'admin@example.com')->value('id')
            ?? User::query()->value('id')
            ?? 1;

        Schedule::query()
            ->where('academic_term_id', $term->id)
            ->update([
                'is_active'  => false,
                'updated_by' => $adminId,
            ]);

        foreach ($this->readCsv($csvPath) as $row) {
            $day       = $this->dayOfWeek($row['day']);
            $roomCode  = trim($row['room_code']);
            $startTime = $this->normalizeTime($row['start_time']);
            $endTime   = $this->normalizeTime($row['end_time']);

            $room = Room::query()->firstOrCreate(
                ['code' => $roomCode],
                [
                    'name'          => $roomCode,
                    'room_type'     => RoomType::Classroom,
                    'floor'         => $this->roomFloor($roomCode),
                    'capacity'      => 40,
                    'display_order' => $this->roomDisplayOrder($roomCode),
                    'is_active'     => true,
                    'created_by'    => $adminId,
                    'updated_by'    => $adminId,
                ]
            );

            Schedule::query()->updateOrCreate(
                [
                    'academic_term_id' => $term->id,
                    'room_id'          => $room->id,
                    'subject_code'     => trim($row['subject_code']),
                    'section'          => trim($row['section']),
                    'day_of_week'      => $day->value,
                    'start_time'       => $startTime,
                    'end_time'         => $endTime,
                ],
                [
                    'subject_title'   => trim($row['subject_title']),
                    'instructor_name' => trim($row['instructor_name']) ?: 'TBA',
                    'is_active'       => true,
                    'created_by'      => $adminId,
                    'updated_by'      => $adminId,
                ]
            );
        }
    }

    /**
     * @return iterable<array<string, string>>
     */
    private function readCsv(string $path): iterable
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);

        $headers = null;

        foreach ($file as $lineNumber => $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(
                    fn ($header) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header))),
                    $row
                );

                continue;
            }

            if (count($row) !== count($headers)) {
                throw new RuntimeException("Invalid CSV column count on line " . ($lineNumber + 1));
            }

            $record = array_combine(
                $headers,
                array_map(fn ($value) => trim((string) $value), $row)
            );

            if ($record === false || implode('', $record) === '') {
                continue;
            }

            foreach (['day', 'room_code', 'start_time', 'end_time', 'subject_code', 'subject_title', 'section'] as $requiredColumn) {
                if (($record[$requiredColumn] ?? '') === '') {
                    throw new RuntimeException("Missing required CSV value '{$requiredColumn}' on line " . ($lineNumber + 1));
                }
            }

            yield $record;
        }
    }

    private function dayOfWeek(string $day): DayOfWeek
    {
        return match (strtolower(trim($day))) {
            'monday'    => DayOfWeek::Monday,
            'tuesday'   => DayOfWeek::Tuesday,
            'wednesday' => DayOfWeek::Wednesday,
            'thursday'  => DayOfWeek::Thursday,
            'friday'    => DayOfWeek::Friday,
            'saturday'  => DayOfWeek::Saturday,
            default     => throw new RuntimeException("Unsupported day value: {$day}"),
        };
    }

    private function normalizeTime(string $time): string
    {
        $time = strtoupper(trim($time));

        foreach (['H:i:s', 'H:i', 'g:i A', 'g:iA', 'h:i A', 'h:iA'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $time);

            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->format('H:i:s');
            }
        }

        throw new RuntimeException("Unsupported time value: {$time}");
    }

    private function roomFloor(string $roomCode): ?int
    {
        if (preg_match('/CEA(\d)/i', $roomCode, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function roomDisplayOrder(string $roomCode): int
    {
        return match (strtoupper($roomCode)) {
            'CEA302' => 1,
            'CEA300' => 2,
            'CEA316' => 3,
            'CEA315' => 4,
            'CEA314' => 5,
            'CEA313' => 6,
            'CEA312' => 7,
            'CEA311' => 8,
            'CEA310' => 9,
            'CEA413' => 10,
            'CEA207' => 11,
            default  => 999,
        };
    }
}
