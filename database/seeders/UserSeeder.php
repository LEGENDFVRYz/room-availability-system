<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Dummy Admin 1
        User::updateOrCreate(
            ['email' => 'admin@example.com'], // Search by this unique attribute
            [
                'name'      => 'Admin User',
                'password'  => Hash::make('test123'),
                'role'      => UserRole::Admin, // Assumes 'role' is cast to this Enum in the User model
                'is_active' => true,
            ]
        );
        
        // Dummy Admin 2 for transferring role purposes
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'      => 'Test User',
                'password'  => Hash::make('test123'),
                'role'      => UserRole::Admin,
                'is_active' => false,
            ]
        );
    }
}
