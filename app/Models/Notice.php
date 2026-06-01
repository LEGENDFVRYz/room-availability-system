<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'tbl_notices';

    protected $fillable = [
        'title',
        'body',
        'type',
        'source_type',
        'source_id',
        'room_id',
        'metadata',
        'starts_at',
        'ends_at',
        'status',
        'is_pinned',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
    ];

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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeVisible(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('starts_at', '<=', $now)
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }

    public function getDesignAttribute(): string
    {
        return match ($this->source_type) {
            'schedule_exception' => 'exception',
            'room_override'      => 'override',
            default              => 'general',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'academic_term'      => 'Academic Term',
            'schedule_update'    => 'Schedule Update',
            'class_cancellation' => 'Class Cancellation',
            'room_change'        => 'Room Change',
            'special_class'      => 'Special Class',
            'room_maintenance'   => 'Maintenance',
            'room_reserved'      => 'Room Reserved',
            default              => 'General Notice',
        };
    }

    public function toKioskAnnouncement(): array
    {
        $metadata = $this->metadata ?? [];

        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'body'       => $this->body,
            'type'       => $this->type,
            'typeLabel'  => $this->type_label,
            'sourceType' => $this->source_type,
            'design'     => $this->design,
            'room'       => $metadata['room_label'] ?? $this->room?->code,
            'schedule'   => $metadata['schedule_label'] ?? null,
            'postedAt'   => $this->created_at?->format('M d, Y g:i A'),
            'expiresAt'  => $this->ends_at?->format('M d, Y g:i A'),
            'isPinned'   => $this->is_pinned,
        ];
    }
}
