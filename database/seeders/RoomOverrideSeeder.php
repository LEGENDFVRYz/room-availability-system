<?php

namespace Database\Seeders;

use App\Enums\RoomOverrideStatus;
use App\Models\Room;
use App\Models\RoomOverride;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoomOverrideSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Retrieve an admin user to attach as the creator
        $admin = User::first() ?? User::factory()->create();

        // Helper to grab room IDs safely
        $room310 = Room::where('name', 'like', '%310%')->value('id') ?? 1;
        $room311 = Room::where('name', 'like', '%311%')->value('id') ?? 2;
        $room312 = Room::where('name', 'like', '%312%')->value('id') ?? 3;
        $room313 = Room::where('name', 'like', '%313%')->value('id') ?? 4;

        $overrides = [
            // ROOM 311 - Unavailable (Active, Indefinite)
            [
                'room_id'    => $room311,
                'status'     => RoomOverrideStatus::Unavailable,
                'reason'     => 'Room locked due to key control issue.',
                'starts_at'  => Carbon::parse('2026-05-17 14:30:00'),
                'ends_at'    => null, 
                'is_active'  => true,
                'created_by' => $admin->id,
            ],
            
            // ROOM 310 - Maintenance (Active, Ends Today)
            [
                'room_id'    => $room310,
                'status'     => RoomOverrideStatus::Maintenance,
                'reason'     => 'Projector replacement and testing.',
                'starts_at'  => Carbon::parse('2026-05-17 15:30:00'),
                'ends_at'    => Carbon::parse('2026-05-17 19:30:00'),
                'is_active'  => true,
                'created_by' => $admin->id,
            ],
            
            // ROOM 312 - Reserved (Upcoming)
            [
                'room_id'    => $room312,
                'status'     => RoomOverrideStatus::Reserved,
                'reason'     => 'Faculty meeting.',
                'starts_at'  => Carbon::parse('2026-05-17 18:30:00'),
                'ends_at'    => Carbon::parse('2026-05-17 20:30:00'),
                'is_active'  => true,
                'created_by' => $admin->id,
            ],
            
            // ROOM 313 - Maintenance (Archived/Expired)
            [
                'room_id'    => $room313,
                'status'     => RoomOverrideStatus::Maintenance,
                'reason'     => 'Previous network inspection.',
                'starts_at'  => Carbon::parse('2026-05-15 16:30:00'),
                'ends_at'    => Carbon::parse('2026-05-15 18:30:00'),
                'is_active'  => false,                                  // explicitly archived for testing
                'created_by' => $admin->id,
            ],
        ];

        foreach ($overrides as $override) {
            RoomOverride::create($override);
        }
    }
}
