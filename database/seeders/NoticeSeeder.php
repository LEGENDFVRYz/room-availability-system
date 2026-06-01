<?php

namespace Database\Seeders;

use App\Models\Notice;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class NoticeSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()->value('id');

        $rooms = Room::query()
            ->whereIn('code', ['CEA 300', 'CEA 301', 'CEA 207', 'CEA 413'])
            ->get()
            ->keyBy('code');

        $now = now();
        $defaultEnd = $now->copy()->addDays(7);

        $notices = [
            [
                'title'          => 'New academic term is now active',
                'body'           => 'The current academic term has been updated. Room schedules now follow the active term configuration.',
                'type'           => 'academic_term',
                'design_variant' => 'general',
                'source_type'    => 'academic_term',
                'source_id'      => null,
                'room_id'        => null,
                'metadata'       => [
                    'audience' => 'students_faculty_viewers',
                ],
                'is_pinned'      => true,
                'ends_at'        => null,
            ],
            [
                'title'          => 'Room schedule has been updated',
                'body'           => 'Some weekly class schedules were revised. Please check the latest room availability before proceeding to a room.',
                'type'           => 'schedule_update',
                'design_variant' => 'general',
                'source_type'    => 'schedule',
                'source_id'      => null,
                'room_id'        => null,
                'metadata'       => [
                    'audience' => 'students_faculty_viewers',
                ],
                'is_pinned'      => false,
                'ends_at'        => $defaultEnd,
            ],
            [
                'title'          => 'BS CPE 4A - Embedded Systems class cancelled today',
                'body'           => 'The scheduled class for this section is cancelled today only. Please check the room board before proceeding, as the room may become available after the protected schedule window.',
                'type'           => 'class_cancellation',
                'design_variant' => 'exception',
                'source_type'    => 'schedule_exception',
                'source_id'      => null,
                'room_id'        => $rooms->get('CEA 301')?->id,
                'metadata'       => [
                    'section'        => 'BS CPE 4A',
                    'subject_code'   => 'CPE 411',
                    'subject_title'  => 'Embedded Systems',
                    'room_label'     => 'CEA 301',
                    'schedule_label' => '10:00 AM to 12:00 PM',
                ],
                'is_pinned'      => false,
                'ends_at'        => $defaultEnd,
            ],
            [
                'title'          => 'BS CPE 3A - Digital Logic moved to CEA 413',
                'body'           => 'A same day room change was approved for this section. Students and faculty should proceed to the replacement room shown below.',
                'type'           => 'room_change',
                'design_variant' => 'exception',
                'source_type'    => 'schedule_exception',
                'source_id'      => null,
                'room_id'        => $rooms->get('CEA 413')?->id,
                'metadata'       => [
                    'section'         => 'BS CPE 3A',
                    'subject_code'    => 'CPE 321',
                    'subject_title'   => 'Digital Logic',
                    'from_room_label' => 'CEA 207',
                    'to_room_label'   => 'CEA 413',
                    'room_label'      => 'CEA 207 → CEA 413',
                    'schedule_label'  => '1:00 PM to 3:00 PM',
                ],
                'is_pinned'      => false,
                'ends_at'        => $defaultEnd,
            ],
            [
                'title'          => 'BS CPE 2B - Data Structures special class added',
                'body'           => 'A special or makeup class was added for this section today. Please follow the assigned room and time shown below.',
                'type'           => 'special_class',
                'design_variant' => 'exception',
                'source_type'    => 'schedule_exception',
                'source_id'      => null,
                'room_id'        => $rooms->get('CEA 300')?->id,
                'metadata'       => [
                    'section'        => 'BS CPE 2B',
                    'subject_code'   => 'CPE 211',
                    'subject_title'  => 'Data Structures',
                    'room_label'     => 'CEA 300',
                    'schedule_label' => '3:00 PM to 5:00 PM',
                ],
                'is_pinned'      => false,
                'ends_at'        => $defaultEnd,
            ],
            [
                'title'          => 'CEA 301 is under maintenance',
                'body'           => 'This room is temporarily unavailable. Normal schedules are overridden while this notice is active.',
                'type'           => 'room_maintenance',
                'design_variant' => 'override',
                'source_type'    => 'room_override',
                'source_id'      => null,
                'room_id'        => $rooms->get('CEA 301')?->id,
                'metadata'       => [
                    'room_label'     => 'CEA 301',
                    'schedule_label' => '8:00 AM to 5:00 PM',
                ],
                'is_pinned'      => false,
                'ends_at'        => $defaultEnd,
            ],
            [
                'title'          => 'CEA 413 reserved for department activity',
                'body'           => 'This room is reserved for an official department activity. Availability is blocked while this notice is active.',
                'type'           => 'room_reserved',
                'design_variant' => 'override',
                'source_type'    => 'room_override',
                'source_id'      => null,
                'room_id'        => $rooms->get('CEA 413')?->id,
                'metadata'       => [
                    'room_label'     => 'CEA 413',
                    'schedule_label' => '9:00 AM to 12:00 PM',
                ],
                'is_pinned'      => false,
                'ends_at'        => $defaultEnd,
            ],
        ];

        foreach ($notices as $notice) {
            Notice::query()->updateOrCreate(
                [
                    'type'  => $notice['type'],
                    'title' => $notice['title'],
                ],
                array_merge([
                    'starts_at'                 => $now->copy()->subHour(),
                    'status'                    => 'published',
                    'is_active'                 => true,
                    'show_on_kiosk'             => true,
                    'show_on_public_dashboard'  => true,
                    'show_on_public_schedule'   => true,
                    'created_by'                => $userId,
                    'updated_by'                => $userId,
                    'published_at'              => $now,
                    'published_by'              => $userId,
                ], $notice)
            );
        }
    }
}
