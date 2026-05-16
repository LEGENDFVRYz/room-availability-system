<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Room;
use App\Models\Schedule;

class ScheduleService
{
    private const COLORS = [
        'blue', 'rose', 'green', 'orange', 'teal', 'violet', 'yellow', 'purple',
    ];


    // -------------------------------------------------------------------------
    //  Read Logics 
    // -------------------------------------------------------------------------

    /**
     * Returns all sections with their schedules for the given term,
     * grouped and sorted by section label.
     */
    public function getSectionsForTerm(AcademicTerm $term): array
    {
        $rows = Schedule::with('room')
            ->where('academic_term_id', $term->id)
            ->where('is_active', true)
            ->orderBy('section')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $result = [];

        foreach ($rows->groupBy('section') as $label => $entries) {
            $colorMap   = [];
            $colorIndex = 0;

            foreach ($entries as $entry) {
                if (! isset($colorMap[$entry->subject_code])) {
                    $colorMap[$entry->subject_code] = self::COLORS[$colorIndex % count(self::COLORS)];
                    $colorIndex++;
                }
            }

            $result[] = [
                'label'     => $label,
                'year'      => $this->extractYear($label),
                'schedules' => $entries->map(fn ($s) => [
                    'id'           => $s->id,
                    'subject'      => $s->subject_title,
                    'subject_code' => $s->subject_code,
                    'section'      => $s->section,
                    'day'          => $s->day_of_week->value - 1, // 0 = Monday
                    'start_time'   => substr($s->start_time, 0, 5),
                    'end_time'     => substr($s->end_time, 0, 5),
                    'room'         => $s->room?->name,
                    'room_id'      => $s->room_id,
                    'instructor'   => $s->instructor_name,
                    'color'        => $colorMap[$s->subject_code],
                ])->values()->all(),
            ];
        }

        usort($result, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $result;
    }

    /**
     * Returns all active rooms with their schedules for the given term.
     * Schedules are colored by section so each section has a consistent
     * identity across rooms.
     */
    public function getRoomSchedulesForTerm(AcademicTerm $term): array
    {
        $sectionColors = $this->buildSectionColorMap($term);

        $byRoom = Schedule::where('academic_term_id', $term->id)
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('room_id');

        return Room::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(function (Room $room) use ($byRoom, $sectionColors) {
                $entries = $byRoom->get($room->id, collect());

                return [
                    'id'        => $room->id,
                    'code'      => $room->code,
                    'name'      => $room->name,
                    'type'      => $room->room_type->value,
                    'schedules' => $entries->map(fn ($s) => [
                        'id'           => $s->id,
                        'subject'      => $s->subject_title,
                        'subject_code' => $s->subject_code,
                        'section'      => $s->section,
                        'day'          => $s->day_of_week->value - 1, // 0 = Monday
                        'start_time'   => substr($s->start_time, 0, 5),
                        'end_time'     => substr($s->end_time, 0, 5),
                        'instructor'   => $s->instructor_name,
                        'room_id'      => $s->room_id,
                        'color'        => $sectionColors[$s->section] ?? 'blue',
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** Assigns a stable color to each section label, sorted alphabetically. */
    private function buildSectionColorMap(AcademicTerm $term): array
    {
        $sections = Schedule::where('academic_term_id', $term->id)
            ->where('is_active', true)
            ->distinct()
            ->pluck('section')
            ->sort()
            ->values();

        $map = [];
        foreach ($sections as $i => $section) {
            $map[$section] = self::COLORS[$i % count(self::COLORS)];
        }

        return $map;
    }


    // -------------------------------------------------------------------------
    //  Write Logics 
    // -------------------------------------------------------------------------

    public function create(array $validated, int $termId, int $userId): array
    {
        if ($this->hasConflict(
            $validated['room_id'],
            $validated['day_of_week'],
            $validated['start_time'],
            $validated['end_time'],
            $termId,
        )) {
            return ['conflict' => true];
        }

        Schedule::create([
            'academic_term_id' => $termId,
            'room_id'          => $validated['room_id'],
            'subject_code'     => $validated['subject_code'],
            'subject_title'    => $validated['subject_title'],
            'section'          => $validated['section'],
            'instructor_name'  => $validated['instructor_name'] ?? null,
            'day_of_week'      => $validated['day_of_week'],
            'start_time'       => $validated['start_time'] . ':00',
            'end_time'         => $validated['end_time'] . ':00',
            'is_active'        => true,
            'created_by'       => $userId,
            'updated_by'       => $userId,
        ]);

        return ['conflict' => false];
    }

    public function update(Schedule $schedule, array $validated, int $userId): array
    {
        if ($this->hasConflict(
            $validated['room_id'],
            $validated['day_of_week'],
            $validated['start_time'],
            $validated['end_time'],
            $schedule->academic_term_id,
            $schedule->id,
        )) {
            return ['conflict' => true];
        }

        $schedule->update([
            'room_id'         => $validated['room_id'],
            'subject_code'    => $validated['subject_code'],
            'subject_title'   => $validated['subject_title'],
            'section'         => $validated['section'],
            'instructor_name' => $validated['instructor_name'] ?? null,
            'day_of_week'     => $validated['day_of_week'],
            'start_time'      => $validated['start_time'] . ':00',
            'end_time'        => $validated['end_time'] . ':00',
            'updated_by'      => $userId,
        ]);

        return ['conflict' => false];
    }

    public function delete(Schedule $schedule): void
    {
        $schedule->delete();
    }


    // -------------------------------------------------------------------------
    //  Validation Logics (conflic of schedules)
    // -------------------------------------------------------------------------

    /**
     * Returns true if the given room/day/time window overlaps any active schedule
     * in the same academic term.
     *
     * Conflict rule: new_start < existing_end AND new_end > existing_start
     */
    public function hasConflict(
        int     $roomId,
        int     $dayOfWeek,
        string  $startTime,
        string  $endTime,
        int     $termId,
        ?int    $excludeId = null,
    ): bool {
        $query = Schedule::where('room_id', $roomId)
            ->where('academic_term_id', $termId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }


    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------
    
    private function extractYear(string $section): int
    {
        if (preg_match('/(\d+)-\d+$/', $section, $m)) {
            return (int) $m[1];
        }

        return 1;
    }
}
