<?php

namespace App\Services;

use App\Models\RoomOverride;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RoomOverrideService
{
    public function create(array $data, int $userId): RoomOverride
    {
        $payload = $this->normalizePayload($data);

        $this->ensureNoOverlappingOverride(
            roomId: $payload['room_id'],
            startsAt: $payload['starts_at'],
            endsAt: $payload['ends_at'],
        );

        return RoomOverride::create([
            ...$payload,
            'is_active'  => true,
            'created_by' => $userId,
            'updated_by' => null,
        ]);
    }

    public function update(RoomOverride $override, array $data, int $userId): RoomOverride
    {
        $this->ensureCanBeUpdated($override);

        $payload = $this->normalizePayload($data);

        $this->ensureNoOverlappingOverride(
            roomId: $payload['room_id'],
            startsAt: $payload['starts_at'],
            endsAt: $payload['ends_at'],
            ignoreId: $override->id,
        );

        $override->update([
            ...$payload,
            'updated_by' => $userId,
        ]);

        return $override->refresh();
    }

    /**
     * One-button clear behavior:
     * - Upcoming override: deactivate it because it has not happened yet.
     * - Active/started override: end it now so history keeps the actual window.
     */
    public function clear(RoomOverride $override, int $userId): RoomOverride
    {
        if ($this->isArchived($override)) {
            return $override;
        }

        if ($override->starts_at->isFuture()) {
            $override->update([
                'is_active'  => false,
                'updated_by' => $userId,
            ]);

            return $override->refresh();
        }

        $override->update([
            'ends_at'    => now(),
            'updated_by' => $userId,
        ]);

        return $override->refresh();
    }

    private function normalizePayload(array $data): array
    {
        $indefinite = (bool) ($data['indefinite'] ?? false);

        return [
            'room_id'   => (int) $data['room_id'],
            'status'    => $data['status'],
            'reason'    => filled($data['reason'] ?? null) ? $data['reason'] : null,
            'starts_at' => Carbon::parse($data['starts_at']),
            'ends_at'   => $indefinite || blank($data['ends_at'] ?? null)
                ? null
                : Carbon::parse($data['ends_at']),
        ];
    }

    private function ensureCanBeUpdated(RoomOverride $override): void
    {
        if ($this->isArchived($override)) {
            throw ValidationException::withMessages([
                'override' => 'Archived room overrides cannot be edited.',
            ]);
        }
    }

    private function ensureNoOverlappingOverride(
        int $roomId,
        Carbon $startsAt,
        ?Carbon $endsAt,
        ?int $ignoreId = null,
    ): void {
        $hasOverlap = RoomOverride::query()
            ->where('room_id', $roomId)
            ->where('is_active', true)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->when($endsAt, fn ($query) => $query->where('starts_at', '<', $endsAt))
            ->where(function ($query) use ($startsAt) {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $startsAt);
            })
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'starts_at' => 'This room already has an active or scheduled override that overlaps this time window.',
            ]);
        }
    }

    private function isArchived(RoomOverride $override): bool
    {
        return ! $override->is_active
            || ($override->ends_at !== null && $override->ends_at->isPast());
    }
}
