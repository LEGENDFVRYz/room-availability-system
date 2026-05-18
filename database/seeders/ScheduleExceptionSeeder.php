<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\Schedule;
use App\Models\ScheduleException;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ScheduleExceptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $term = AcademicTerm::query()
        //     ->where('is_current', true)
        //     ->firstOrFail();

        // $date = Carbon::today()->toDateString();

        // $exceptions = [
        //     [
        //         'lookup' => [
        //             'subject_code' => 'CMPE 361',
        //             'section' => 'BSCPE 1-IP',
        //             'start_time' => '12:30:00',
        //             'end_time' => '14:00:00',
        //         ],
        //         'data' => [
        //             'room_id' => 1,
        //             'event_type' => 'cancellation',
        //             'status' => 'cancelled',
        //             'subject_code' => 'CMPE 361',
        //             'subject_title' => 'Enterprise Networking',
        //             'section' => 'BSCPE 1-IP',
        //             'instructor_name' => 'J. Binuya',
        //             'start_time' => '12:30:00',
        //             'end_time' => '14:00:00',
        //             'reason' => 'Instructor unavailable today.',
        //         ],
        //     ],
        //     [
        //         'lookup' => [
        //             'subject_code' => 'CMPE 011',
        //             'section' => 'BSCPE 1-3',
        //             'start_time' => '14:00:00',
        //             'end_time' => '16:30:00',
        //         ],
        //         'data' => [
        //             'room_id' => 3,
        //             'event_type' => 'room_change',
        //             'status' => 'pending',
        //             'subject_code' => 'CMPE 011',
        //             'subject_title' => 'Computer Programming',
        //             'section' => 'BSCPE 1-3',
        //             'instructor_name' => 'J. Nicolas',
        //             'start_time' => '14:00:00',
        //             'end_time' => '16:30:00',
        //             'reason' => 'Original room unavailable.',
        //         ],
        //     ],
        //     [
        //         'lookup' => null,
        //         'data' => [
        //             'schedule_id' => null,
        //             'room_id' => 6,
        //             'event_type' => 'special_class',
        //             'status' => 'pending',
        //             'subject_code' => 'CMPE 499',
        //             'subject_title' => 'Capstone Consultation',
        //             'section' => 'BSCPE 4-2',
        //             'instructor_name' => 'M. Bautista',
        //             'start_time' => '13:00:00',
        //             'end_time' => '15:00:00',
        //             'reason' => 'Special consultation session.',
        //         ],
        //     ],
        //     [
        //         'lookup' => null,
        //         'data' => [
        //             'schedule_id' => null,
        //             'room_id' => 9,
        //             'event_type' => 'makeup_class',
        //             'status' => 'pending',
        //             'subject_code' => 'ENSC 411',
        //             'subject_title' => 'Technopreneurship 101',
        //             'section' => 'BSCPE 3-2',
        //             'instructor_name' => 'J. Tena',
        //             'start_time' => '18:00:00',
        //             'end_time' => '20:00:00',
        //             'reason' => 'Makeup class for missed session.',
        //         ],
        //     ],
        // ];

        // foreach ($exceptions as $exception) {
        //     $schedule = null;

        //     if ($exception['lookup']) {
        //         $schedule = Schedule::query()
        //             ->where('academic_term_id', $term->id)
        //             ->where('subject_code', $exception['lookup']['subject_code'])
        //             ->where('section', $exception['lookup']['section'])
        //             ->where('start_time', $exception['lookup']['start_time'])
        //             ->where('end_time', $exception['lookup']['end_time'])
        //             ->first();

        //         if (! $schedule) {
        //             $this->command?->warn(
        //                 "Skipped {$exception['lookup']['subject_code']} {$exception['lookup']['section']} because no matching schedule exists."
        //             );

        //             continue;
        //         }
        //     }

        //     ScheduleException::query()->updateOrCreate(
        //         [
        //             'academic_term_id' => $term->id,
        //             'event_date' => $date,
        //             'event_type' => $exception['data']['event_type'],
        //             'subject_code' => $exception['data']['subject_code'],
        //             'section' => $exception['data']['section'],
        //             'start_time' => $exception['data']['start_time'],
        //         ],
        //         [
        //             ...$exception['data'],
        //             'schedule_id' => $exception['data']['schedule_id'] ?? $schedule?->id,
        //             'academic_term_id' => $term->id,
        //             'event_date' => $date,
        //             'auto_cancel_at' => null,
        //             'claimed_at' => null,
        //             'created_by' => 1,
        //             'updated_by' => 1,
        //         ]
        //     );
        // }
    }
}
