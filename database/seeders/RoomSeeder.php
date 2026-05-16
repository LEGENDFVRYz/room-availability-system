<?php

namespace Database\Seeders;

use App\Enums\RoomType;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = 1;

        $rooms = [
            [
                'code'          => 'ROOM 300',
                'name'          => 'CPE Department Office',
                'room_type'     => RoomType::Office,
                'floor'         => 3,
                'capacity'      => null,
                'display_order' => 1,
            ],
            [
                'code'          => 'ROOM 310',
                'name'          => 'CPE Lecture Room 1',
                'room_type'     => RoomType::Classroom,
                'floor'         => 3,
                'capacity'      => 40,
                'display_order' => 2,
            ],
            [
                'code'          => 'ROOM 311',
                'name'          => 'CPE Lecture Room 2',
                'room_type'     => RoomType::Classroom,
                'floor'         => 3,
                'capacity'      => 40,
                'display_order' => 3,
            ],
            [
                'code'          => 'ROOM 312',
                'name'          => 'CPE Lecture Room 3',
                'room_type'     => RoomType::Classroom,
                'floor'         => 3,
                'capacity'      => 40,
                'display_order' => 4,
            ],
            [
                'code'          => 'ROOM 313',
                'name'          => 'CPE Lecture Room 4',
                'room_type'     => RoomType::Classroom,
                'floor'         => 3,
                'capacity'      => 40,
                'display_order' => 5,
            ],
            [
                'code'          => 'ROOM 314',
                'name'          => 'CPE Laboratory 1',
                'room_type'     => RoomType::Laboratory,
                'floor'         => 3,
                'capacity'      => 35,
                'display_order' => 6,
            ],
            [
                'code'          => 'ROOM 315',
                'name'          => 'CPE Laboratory 2',
                'room_type'     => RoomType::Laboratory,
                'floor'         => 3,
                'capacity'      => 35,
                'display_order' => 7,
            ],
            [
                'code'          => 'ROOM 316',
                'name'          => 'CPE Laboratory 3',
                'room_type'     => RoomType::Laboratory,
                'floor'         => 3,
                'capacity'      => 35,
                'display_order' => 8,
            ],
            [
                'code'          => 'ROOM 317',
                'name'          => 'CPE Laboratory 4',
                'room_type'     => RoomType::Laboratory,
                'floor'         => 3,
                'capacity'      => 35,
                'display_order' => 9,
            ],
        ];

        foreach ($rooms as $room) {
            Room::create(array_merge($room, [
                'is_active'  => true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]));
        }
    }
}
