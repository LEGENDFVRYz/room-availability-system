<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Models\AcademicTerm;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $term    = AcademicTerm::where('is_current', true)->firstOrFail();
        $adminId = 1;

        // Keyed by name so room code changes don't break this seeder
        $rooms = Room::pluck('id', 'name');

        $schedules = [
            [
                'room_name'       => 'CPE Lecture Room 1',
                'subject_code'    => 'GEED 101',
                'subject_title'   => 'Understanding the Self',
                'section'         => 'BSCPE 1-1',
                'instructor_name' => 'Prof. Santos',
                'day_of_week'     => DayOfWeek::Monday,
                'start_time'      => '07:30:00',
                'end_time'        => '09:00:00',
            ],
            [
                'room_name'       => 'CPE Lecture Room 1',
                'subject_code'    => 'MATH 101',
                'subject_title'   => 'Calculus 1',
                'section'         => 'BSCPE 1-2',
                'instructor_name' => 'Prof. Reyes',
                'day_of_week'     => DayOfWeek::Monday,
                'start_time'      => '09:00:00',
                'end_time'        => '10:30:00',
            ],
            [
                'room_name'       => 'CPE Lecture Room 1',
                'subject_code'    => 'CMPE 101',
                'subject_title'   => 'Introduction to Computing',
                'section'         => 'BSCPE 1-3',
                'instructor_name' => 'Prof. Cruz',
                'day_of_week'     => DayOfWeek::Wednesday,
                'start_time'      => '13:00:00',
                'end_time'        => '14:30:00',
            ],
            [
                'room_name'       => 'CPE Laboratory 1',
                'subject_code'    => 'CMPE 201',
                'subject_title'   => 'Computer Programming 1',
                'section'         => 'BSCPE 2-1',
                'instructor_name' => 'Prof. Garcia',
                'day_of_week'     => DayOfWeek::Tuesday,
                'start_time'      => '07:30:00',
                'end_time'        => '10:30:00',
            ],
            [
                'room_name'       => 'CPE Laboratory 1',
                'subject_code'    => 'CMPE 201',
                'subject_title'   => 'Computer Programming 1',
                'section'         => 'BSCPE 2-2',
                'instructor_name' => 'Prof. Garcia',
                'day_of_week'     => DayOfWeek::Thursday,
                'start_time'      => '07:30:00',
                'end_time'        => '10:30:00',
            ],
            [
                'room_name'       => 'CPE Laboratory 2',
                'subject_code'    => 'CMPE 301',
                'subject_title'   => 'Data Structures and Algorithms',
                'section'         => 'BSCPE 3-1',
                'instructor_name' => 'Prof. Lim',
                'day_of_week'     => DayOfWeek::Tuesday,
                'start_time'      => '10:30:00',
                'end_time'        => '13:30:00',
            ],
            [
                'room_name'       => 'CPE Laboratory 2',
                'subject_code'    => 'CMPE 401',
                'subject_title'   => 'Embedded Systems',
                'section'         => 'BSCPE 4-1',
                'instructor_name' => 'Prof. Dela Cruz',
                'day_of_week'     => DayOfWeek::Friday,
                'start_time'      => '13:00:00',
                'end_time'        => '16:00:00',
            ],
            [
                'room_name'       => 'CPE Laboratory 3',
                'subject_code'    => 'CMPE 402',
                'subject_title'   => 'Capstone Project 1',
                'section'         => 'BSCPE 4-3',
                'instructor_name' => 'Prof. Mendoza',
                'day_of_week'     => DayOfWeek::Saturday,
                'start_time'      => '07:30:00',
                'end_time'        => '10:30:00',
            ],
        ];

        foreach ($schedules as $data) {
            $roomId = $rooms->get($data['room_name']);

            if (! $roomId) {
                $this->command->warn("Skipping schedule — room \"{$data['room_name']}\" not found.");
                continue;
            }

            Schedule::create([
                'academic_term_id' => $term->id,
                'room_id'          => $roomId,
                'subject_code'     => $data['subject_code'],
                'subject_title'    => $data['subject_title'],
                'section'          => $data['section'],
                'instructor_name'  => $data['instructor_name'],
                'day_of_week'      => $data['day_of_week'],
                'start_time'       => $data['start_time'],
                'end_time'         => $data['end_time'],
                'is_active'        => true,
                'created_by'       => $adminId,
                'updated_by'       => $adminId,
            ]);
        }
    }
}
