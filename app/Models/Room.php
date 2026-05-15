<?php

namespace App\Models;

use App\Enums\RoomType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $table = 'tbl_rooms';

    protected $fillable = [
        'code',
        'name',
        'room_type',
        'floor',
        'capacity',
        'display_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'room_type'     => RoomType::class,
            'capacity'      => 'integer',
            'display_order' => 'integer',
            'is_active'     => 'boolean',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }
}
