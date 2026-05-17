<?php

namespace App\Models;

use App\Enums\RoomOverrideStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomOverride extends Model
{
    use HasFactory;

    protected $table = 'tbl_room_overrides';

    protected $fillable = [
        'room_id',
        'status',
        'reason',
        'starts_at',
        'ends_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status'    => RoomOverrideStatus::class,
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
            'is_active' => 'boolean',
        ];
    }


    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }


    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrentlyActive(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', $at)
            ->where(function (Builder $query) use ($at) {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $at);
            });
    }

    public function scopeForRoom(Builder $query, int|Room $room): Builder
    {
        $roomId = $room instanceof Room ? $room->id : $room;

        return $query->where('room_id', $roomId);
    }
}
