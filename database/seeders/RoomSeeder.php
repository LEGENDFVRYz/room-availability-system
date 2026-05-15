<?php

namespace Database\Seeders;

use App\Enums\RoomType;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            [
                'code'          => 'ROOM 303',
                'name'          => 'CPE Lecture Room 1',
                'room_type'     => RoomType::Classroom,
                'floor'         => 1,
                'capacity'      => 40,
                'display_order' => 1,
            ],
            [
                'code'          => 'ROOM 301',
                'name'          => 'CPE Lecture Room 2',
                'room_type'     => RoomType::Classroom,
                'floor'         => 1,
                'capacity'      => 40,
                'display_order' => 2,
            ],
            [
                'code'          => 'ROOM 302',
                'name'          => 'CPE Laboratory 1',
                'room_type'     => RoomType::Laboratory,
                'floor'         => 2,
                'capacity'      => 35,
                'display_order' => 3,
            ],
            [
                'code'          => 'ROOM 304',
                'name'          => 'CPE Laboratory 2',
                'room_type'     => RoomType::Laboratory,
                'floor'         => 2,
                'capacity'      => 35,
                'display_order' => 4,
            ],
            [
                'code'          => 'ROOM 305',
                'name'          => 'CPE Laboratory 3',
                'room_type'     => RoomType::Laboratory,
                'floor'         => 3,
                'capacity'      => 35,
                'display_order' => 5,
            ],
            [
                'code'          => 'ROOM 312',
                'name'          => 'CPE Department Office',
                'room_type'     => RoomType::Office,
                'floor'         => 3,
                'capacity'      => null,
                'display_order' => 6,
            ],
        ];

        $adminId = 1;

        foreach ($rooms as $room) {
            Room::create(array_merge($room, [
                'is_active'  => true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]));
        }
    }
}
