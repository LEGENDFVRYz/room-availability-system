<?php

namespace Database\Seeders;

use App\Enums\RoomType;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@example.com')->value('id')
            ?? User::query()->value('id')
            ?? 1;

        $rooms = [
            ['code' => 'CEA302', 'name' => 'Laboratory / Lecture Room', 'room_type' => RoomType::Laboratory, 'floor' => 3, 'capacity' => 40, 'display_order' => 1],
            ['code' => 'CEA300', 'name' => 'Laboratory / Lecture Room', 'room_type' => RoomType::Laboratory, 'floor' => 3, 'capacity' => 40, 'display_order' => 2],
            ['code' => 'CEA316', 'name' => 'Laboratory / Lecture Room', 'room_type' => RoomType::Laboratory, 'floor' => 3, 'capacity' => 40, 'display_order' => 3],
            ['code' => 'CEA315', 'name' => 'Laboratory / Lecture Room', 'room_type' => RoomType::Laboratory, 'floor' => 3, 'capacity' => 40, 'display_order' => 4],
            ['code' => 'CEA314', 'name' => 'Laboratory / Lecture Room', 'room_type' => RoomType::Laboratory, 'floor' => 3, 'capacity' => 40, 'display_order' => 5],
            ['code' => 'CEA313', 'name' => 'Laboratory / Lecture Room', 'room_type' => RoomType::Laboratory, 'floor' => 3, 'capacity' => 40, 'display_order' => 6],
            ['code' => 'CEA312', 'name' => 'Laboratory / Lecture Room', 'room_type' => RoomType::Laboratory, 'floor' => 3, 'capacity' => 40, 'display_order' => 7],
            ['code' => 'CEA311', 'name' => 'Laboratory / Lecture Room', 'room_type' => RoomType::Laboratory, 'floor' => 3, 'capacity' => 40, 'display_order' => 8],
            ['code' => 'CEA310', 'name' => 'Laboratory / Lecture Room', 'room_type' => RoomType::Laboratory, 'floor' => 3, 'capacity' => 40, 'display_order' => 9],
            ['code' => 'CEA413', 'name' => 'Lecture Room', 'room_type' => RoomType::Classroom, 'floor' => 4, 'capacity' => 40, 'display_order' => 10],
            ['code' => 'CEA207', 'name' => 'Drafting / Drawing Room', 'room_type' => RoomType::Classroom, 'floor' => 2, 'capacity' => 40, 'display_order' => 11],
        ];

        foreach ($rooms as $room) {
            Room::query()->updateOrCreate(
                ['code' => $room['code']],
                array_merge($room, [
                    'is_active'  => true,
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ])
            );
        }
    }
}
