<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'role'          => UserRole::class,
            'is_active'     => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }


    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------
    public function createdRooms(): HasMany
    {
        return $this->hasMany(Room::class, 'created_by');
    }

    public function createdSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'created_by');
    }

    public function createdRoomOverrides(): HasMany
    {
        return $this->hasMany(RoomOverride::class, 'created_by');
    }

    public function updatedRoomOverrides(): HasMany
    {
        return $this->hasMany(RoomOverride::class, 'updated_by');
    }

    public function createdScheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class, 'created_by');
    }

    public function updatedScheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class, 'updated_by');
    }
}
