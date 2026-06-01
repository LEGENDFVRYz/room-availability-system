<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed } from 'vue';
import type { DailySlot, DailySlotStatus, DailySlotType, Room, YearLevel } from '@/pages/Admin/Operations/Components/type';

const EVENT_TYPE_LABEL: Record<DailySlotType, string> = {
    regular: 'Regular',
    cancellation: 'Cancellation',
    room_change: 'Room Change',
    special_class: 'Special Class',
    makeup_class: 'Makeup Class',
    maintenance: 'Maintenance',
    unavailable: 'Unavailable',
    reserved: 'Reserved',
};

const STATUS_LABEL: Record<DailySlotStatus, string> = {
    scheduled: 'Awaiting claim',
    pending: 'Awaiting claim',
    ongoing: 'Occupied',
    completed: 'Finished',
    cancelled: 'Cancelled',
    unclaimed: 'Unclaimed',
    auto_cancelled: 'Auto-cancelled',
    maintenance: 'Maintenance',
    unavailable: 'Unavailable',
    reserved: 'Reserved',
};

const EXCEPTION_BADGE_CLASS = 'border border-pup-maroon/30 bg-white text-pup-maroon';

const EVENT_BADGE: Record<DailySlotType, string> = {
    regular: 'bg-sky-100 text-sky-700',
    cancellation: 'border border-gray-300 bg-gray-50 text-gray-500',
    room_change: EXCEPTION_BADGE_CLASS,
    special_class: EXCEPTION_BADGE_CLASS,
    makeup_class: EXCEPTION_BADGE_CLASS,
    maintenance: 'bg-status-maintenance-bg text-status-maintenance',
    unavailable: 'bg-pup-gray-200 text-pup-gray-800',
    reserved: 'bg-status-reserved-bg text-status-reserved',
};

const STATUS_BADGE: Record<DailySlotStatus, string> = {
    scheduled: 'bg-gray-50 text-gray-600',
    pending: 'bg-gray-50 text-gray-600',
    ongoing: 'bg-status-occupied-bg text-status-occupied',
    completed: 'bg-gray-100 text-gray-600',
    cancelled: 'bg-slate-100 text-slate-600',
    unclaimed: 'bg-slate-100 text-slate-600',
    auto_cancelled: 'bg-rose-50 text-rose-700',
    maintenance: 'bg-status-maintenance-bg text-status-maintenance',
    unavailable: 'bg-pup-gray-200 text-pup-gray-800',
    reserved: 'bg-status-reserved-bg text-status-reserved',
};

const YEAR_LEVEL_CLASS: Record<YearLevel, string> = {
    '1': 'border-sky-300 bg-sky-50 text-sky-950',
    '2': 'border-emerald-300 bg-emerald-50 text-emerald-950',
    '3': 'border-violet-300 bg-violet-50 text-violet-950',
    '4': 'border-pup-maroon/30 bg-pup-maroon-pale text-pup-maroon-deep',
    unknown: 'border-gray-200 bg-gray-50 text-gray-800',
};

const props = defineProps<{
    slot: DailySlot | null;
    rooms: Room[];
    allSlots: DailySlot[];
}>();

const emit = defineEmits<{
    close: [];
}>();

function parseMinutes(time: string): number {
    const [hour, minute] = time.split(':').map(Number);
    return hour * 60 + minute;
}

function formatTime(time: string): string {
    const [hour, minute] = time.split(':').map(Number);
    return `${hour % 12 || 12}:${minute.toString().padStart(2, '0')}${hour < 12 ? 'AM' : 'PM'}`;
}

function normalizeTimeValue(value?: string | null): string | null {
    if (!value) return null;

    const directTime = value.match(/^(\d{2}:\d{2})/);
    if (directTime) return directTime[1];

    const embeddedTime = value.match(/[T\s](\d{2}:\d{2})/);
    if (embeddedTime) return embeddedTime[1];

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
}

function isOverrideSlot(slot: DailySlot): boolean {
    return slot.source === 'override';
}

function isClassSlot(slot: DailySlot): boolean {
    return slot.source !== 'override';
}

function isExceptionSlot(slot: DailySlot): boolean {
    return slot.source === 'exception' || ['cancellation', 'room_change', 'special_class', 'makeup_class'].includes(slot.event_type);
}

function isCancelledSlot(slot: DailySlot): boolean {
    return slot.event_type === 'cancellation' || ['cancelled', 'auto_cancelled', 'unclaimed'].includes(slot.status);
}

function effectiveEndTime(slot: DailySlot): string {
    if (!isClassSlot(slot) || slot.status !== 'completed') return slot.end_time;

    const actualEnd = normalizeTimeValue(slot.actual_end);
    if (!actualEnd) return slot.end_time;

    return parseMinutes(actualEnd) > parseMinutes(slot.start_time) && parseMinutes(actualEnd) < parseMinutes(slot.end_time)
        ? actualEnd
        : slot.end_time;
}

function isTrimmedByActualEnd(slot: DailySlot): boolean {
    return effectiveEndTime(slot) !== slot.end_time;
}

function formatTimeRange(slot: DailySlot): string {
    return `${formatTime(slot.start_time)}–${formatTime(effectiveEndTime(slot))}`;
}

function formatOriginalEnd(slot: DailySlot): string {
    return formatTime(slot.end_time);
}

function formatClaimDeadline(slot: DailySlot): string {
    if (slot.claim_deadline_time) return formatTime(slot.claim_deadline_time);
    if (slot.claim_deadline_at) {
        const normalized = normalizeTimeValue(slot.claim_deadline_at);
        if (normalized) return formatTime(normalized);
    }

    return 'the claim deadline';
}

function yearLevel(slot: DailySlot): YearLevel {
    const section = slot.section ?? '';
    const bscpeMatch = section.match(/BSCPE\s*([1-4])/i);
    const fallbackMatch = section.match(/(?:^|\s)([1-4])(?:[-\s]|$)/);
    const value = bscpeMatch?.[1] ?? fallbackMatch?.[1];

    return ['1', '2', '3', '4'].includes(value ?? '') ? (value as YearLevel) : 'unknown';
}

function statusDotClass(slot: DailySlot): string {
    if (!isClassSlot(slot)) return 'hidden';

    if (slot.status === 'ongoing') return 'border-status-occupied bg-status-occupied';
    if (slot.status === 'completed') return 'border-status-available bg-status-available';

    return 'border-gray-300 bg-white';
}

function slotsOverlap(first: DailySlot, second: DailySlot): boolean {
    return parseMinutes(first.start_time) < parseMinutes(effectiveEndTime(second))
        && parseMinutes(effectiveEndTime(first)) > parseMinutes(second.start_time);
}

function overlappingOverrides(slot: DailySlot, slots: DailySlot[]): DailySlot[] {
    if (isOverrideSlot(slot)) return [];

    return slots.filter((candidate) => {
        return isOverrideSlot(candidate)
            && candidate.room_id === slot.room_id
            && slotsOverlap(slot, candidate);
    });
}

function blockingOverride(slot: DailySlot, slots: DailySlot[]): DailySlot | null {
    const overrides = overlappingOverrides(slot, slots);
    if (overrides.length === 0) return null;

    const priority: Record<string, number> = {
        maintenance: 1,
        unavailable: 2,
        reserved: 3,
    };

    return [...overrides].sort((a, b) => (priority[a.event_type] ?? 99) - (priority[b.event_type] ?? 99))[0];
}

function blockingOverrideLabel(slot: DailySlot, slots: DailySlot[]): string {
    const override = blockingOverride(slot, slots);
    return override ? `Blocked by ${EVENT_TYPE_LABEL[override.event_type]}` : '';
}

function blockingOverrideDetails(slot: DailySlot, slots: DailySlot[]): string {
    const override = blockingOverride(slot, slots);
    return override ? `${EVENT_TYPE_LABEL[override.event_type]} · ${formatTimeRange(override)}` : '';
}

function overrideHasAffectedClass(slot: DailySlot, slots: DailySlot[]): boolean {
    if (!isOverrideSlot(slot)) return false;

    return slots.some((candidate) => {
        return isClassSlot(candidate)
            && candidate.room_id === slot.room_id
            && !['cancelled', 'auto_cancelled', 'unclaimed'].includes(candidate.status)
            && slotsOverlap(slot, candidate);
    });
}

function slotBlockClass(slot: DailySlot): string {
    if (isOverrideSlot(slot)) {
        const overrideClass: Record<string, string> = {
            maintenance: 'border-status-maintenance bg-status-maintenance-bg text-pup-gray-800',
            unavailable: 'border-pup-gray-600 bg-pup-gray-200 text-pup-gray-800',
            reserved: 'border-status-reserved-border bg-status-reserved-bg text-status-reserved',
        };

        return overrideClass[slot.event_type] ?? overrideClass.maintenance;
    }

    if (isCancelledSlot(slot)) {
        return 'border-2 border-dashed border-gray-300 bg-gray-50 text-gray-500 opacity-80 shadow-none';
    }

    if (isExceptionSlot(slot)) {
        return 'border-2 border-pup-maroon/70 border-l-4 bg-white text-pup-maroon-deep shadow-sm';
    }

    return YEAR_LEVEL_CLASS[yearLevel(slot)];
}

function slotBlockStyle(slot: DailySlot): Record<string, string> {
    if (isOverrideSlot(slot)) {
        const overlayColor: Record<string, string> = {
            maintenance: 'rgba(107, 114, 128, 0.18)',
            unavailable: 'rgba(31, 41, 55, 0.14)',
            reserved: 'rgba(245, 158, 11, 0.20)',
        };
        const color = overlayColor[slot.event_type] ?? overlayColor.maintenance;

        return {
            backgroundImage: `repeating-linear-gradient(135deg, ${color} 0px, ${color} 3px, transparent 3px, transparent 9px)`,
        };
    }

    if (isCancelledSlot(slot)) {
        return {
            backgroundImage:
                'repeating-linear-gradient(135deg, rgba(107, 114, 128, 0.12) 0px, rgba(107, 114, 128, 0.12) 3px, transparent 3px, transparent 9px)',
        };
    }

    return {};
}

const roomMap = computed(() => new Map(props.rooms.map((room) => [room.id, room])));

function roomLabel(roomId: number): string {
    const room = roomMap.value.get(roomId);
    return room ? `${room.code} · ${room.name}` : `Room #${roomId}`;
}
</script>

<template>
    <Teleport to="body">
        <div v-if="slot" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4">
            <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-pup-maroon">Schedule item</p>
                        <h3 class="mt-1 text-xl font-bold text-gray-900">{{ slot.subject_code }} · {{ slot.subject_title }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ slot.section || 'Room status item' }} · {{ formatTimeRange(slot) }}</p>
                        <p v-if="isTrimmedByActualEnd(slot)" class="mt-1 text-xs font-semibold text-green-700">
                            Ended early · original end {{ formatOriginalEnd(slot) }}
                        </p>
                    </div>

                    <button type="button" @click="emit('close')" class="rounded-full p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                    <div
                        class="rounded-2xl border p-4"
                        :class="slotBlockClass(slot)"
                        :style="slotBlockStyle(slot)"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="EVENT_BADGE[slot.event_type]">
                                {{ EVENT_TYPE_LABEL[slot.event_type] }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold" :class="STATUS_BADGE[slot.status]">
                                <span v-if="slot.source !== 'override'" class="h-2.5 w-2.5 rounded-full border" :class="statusDotClass(slot)" />
                                {{ STATUS_LABEL[slot.status] }}
                            </span>
                        </div>

                        <p class="mt-3 text-sm leading-relaxed opacity-80">
                            {{ roomLabel(slot.room_id) }} · {{ formatTimeRange(slot) }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <p class="text-xs font-semibold uppercase text-gray-400">Room</p>
                            <p class="mt-1 font-semibold text-gray-900">{{ roomLabel(slot.room_id) }}</p>
                            <p v-if="slot.original_room_code" class="mt-1 text-xs font-semibold text-orange-600">
                                Moved from {{ slot.original_room_code }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <p class="text-xs font-semibold uppercase text-gray-400">Instructor</p>
                            <p class="mt-1 font-semibold text-gray-900">{{ slot.instructor_name || '—' }}</p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <p class="text-xs font-semibold uppercase text-gray-400">Type</p>
                            <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="EVENT_BADGE[slot.event_type]">
                                {{ EVENT_TYPE_LABEL[slot.event_type] }}
                            </span>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <p class="text-xs font-semibold uppercase text-gray-400">Status</p>
                            <div class="mt-1 inline-flex items-center gap-2">
                                <span v-if="slot.source !== 'override'" class="h-2.5 w-2.5 rounded-full border" :class="statusDotClass(slot)" />
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="STATUS_BADGE[slot.status]">
                                    {{ STATUS_LABEL[slot.status] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="blockingOverrideLabel(slot, allSlots)"
                        class="rounded-xl border border-pup-maroon/20 bg-pup-maroon-pale px-4 py-3 text-sm text-pup-maroon"
                    >
                        <p class="font-semibold">{{ blockingOverrideLabel(slot, allSlots) }}</p>
                        <p class="mt-1 text-xs text-pup-maroon/80">
                            {{ blockingOverrideDetails(slot, allSlots) }}. The room status override wins over this class during the overlap.
                        </p>
                    </div>

                    <div
                        v-if="isOverrideSlot(slot) && overrideHasAffectedClass(slot, allSlots)"
                        class="rounded-xl border border-pup-maroon/20 bg-pup-maroon-pale px-4 py-3 text-sm text-pup-maroon"
                    >
                        This room override overlaps at least one class. The room status control wins over schedule items during the overlap.
                    </div>

                    <div
                        v-if="slot.status === 'unclaimed'"
                        class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
                    >
                        <p class="font-semibold text-gray-800">Unclaimed class</p>
                        <p class="mt-1 text-xs leading-relaxed text-gray-600">
                            This class was not claimed by {{ formatClaimDeadline(slot) }}. The remaining room time may be reclaimed by another approved class request.
                        </p>
                    </div>

                    <div v-if="slot.reason" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <p class="font-semibold">Reason / Note</p>
                        <p class="mt-1">{{ slot.reason }}</p>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
