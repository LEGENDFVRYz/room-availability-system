<script setup lang="ts">
import type { DailySlot, DailySlotStatus, DailySlotType, RoomWithSlots, YearLevel } from './type';

const START_HOUR = 7;
const END_HOUR = 21;
const HOUR_HEIGHT = 76;
const GRID_HEIGHT = (END_HOUR - START_HOUR) * HOUR_HEIGHT;
const hours = Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, index) => START_HOUR + index);
const hourLines = hours.slice(0, -1);

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

function todayIso(): string {
    return new Date().toISOString().slice(0, 10);
}

function parseMinutes(time: string): number {
    const [hour, minute] = time.split(':').map(Number);
    return hour * 60 + minute;
}

const visibleStartMinutes = START_HOUR * 60;
const visibleEndMinutes = END_HOUR * 60;

function clampMinutes(minutes: number): number {
    return Math.min(Math.max(minutes, visibleStartMinutes), visibleEndMinutes);
}

function slotTop(time: string): number {
    return ((clampMinutes(parseMinutes(time)) - visibleStartMinutes) / 60) * HOUR_HEIGHT;
}

function slotHeight(start: string, end: string): number {
    const startMinutes = clampMinutes(parseMinutes(start));
    const endMinutes = clampMinutes(parseMinutes(end));

    return Math.max(((endMinutes - startMinutes) / 60) * HOUR_HEIGHT, 34);
}

function formatHour(hour: number): string {
    return `${hour % 12 || 12}${hour < 12 ? 'AM' : 'PM'}`;
}

function formatTime(time: string): string {
    const [hour, minute] = time.split(':').map(Number);
    return `${hour % 12 || 12}:${minute.toString().padStart(2, '0')}${hour < 12 ? 'AM' : 'PM'}`;
}

function formatTimeRange(slot: DailySlot): string {
    return `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}`;
}

function yearLevel(slot: DailySlot): YearLevel {
    const section = slot.section ?? '';
    const bscpeMatch = section.match(/BSCPE\s*([1-4])/i);
    const fallbackMatch = section.match(/(?:^|\s)([1-4])(?:[-\s]|$)/);
    const value = bscpeMatch?.[1] ?? fallbackMatch?.[1];

    return ['1', '2', '3', '4'].includes(value ?? '') ? (value as YearLevel) : 'unknown';
}

function isOverrideSlot(slot: DailySlot): boolean {
    return slot.source === 'override';
}

function isClassSlot(slot: DailySlot): boolean {
    return slot.source !== 'override';
}

function isExceptionSlot(slot: DailySlot): boolean {
    return slot.source === 'exception'
        || ['cancellation', 'room_change', 'special_class', 'makeup_class'].includes(slot.event_type);
}

function slotsOverlap(first: DailySlot, second: DailySlot): boolean {
    return parseMinutes(first.start_time) < parseMinutes(second.end_time)
        && parseMinutes(first.end_time) > parseMinutes(second.start_time);
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

function isBlockedByOverride(slot: DailySlot, slots: DailySlot[]): boolean {
    return isClassSlot(slot) && blockingOverride(slot, slots) !== null;
}

function overrideHasAffectedClass(slot: DailySlot, slots: DailySlot[]): boolean {
    if (!isOverrideSlot(slot)) return false;

    return slots.some((candidate) => {
        return isClassSlot(candidate)
            && candidate.room_id === slot.room_id
            && !['cancelled', 'auto_cancelled'].includes(candidate.status)
            && slotsOverlap(slot, candidate);
    });
}

function isCancelledSlot(slot: DailySlot): boolean {
    return slot.event_type === 'cancellation' || ['cancelled', 'auto_cancelled'].includes(slot.status);
}

function statusDotClass(slot: DailySlot): string {
    if (!isClassSlot(slot)) return 'hidden';

    if (slot.status === 'ongoing') {
        return 'border-status-occupied bg-status-occupied';
    }

    if (slot.status === 'completed') {
        return 'border-status-available bg-status-available';
    }

    return 'border-gray-300 bg-white';
}

function slotBlockClass(slot: DailySlot, slots: DailySlot[]): string {
    if (isOverrideSlot(slot)) {
        const overlapState = overrideHasAffectedClass(slot, slots)
            ? 'z-50 shadow-sm group-hover/room:z-10 group-hover/room:opacity-30 group-hover/room:pointer-events-none'
            : 'z-50';

        const overrideClass: Record<string, string> = {
            maintenance: 'border-status-maintenance bg-status-maintenance-bg text-pup-gray-800',
            unavailable: 'border-pup-gray-600 bg-pup-gray-200 text-pup-gray-800',
            reserved: 'border-status-reserved-border bg-status-reserved-bg text-status-reserved',
        };

        return `${overrideClass[slot.event_type] ?? overrideClass.maintenance} ${overlapState}`;
    }

    const blockedState = isBlockedByOverride(slot, slots)
        ? 'z-20 opacity-45 group-hover/room:z-40 group-hover/room:opacity-100 group-hover/room:shadow-md'
        : 'z-30';

    if (isCancelledSlot(slot)) {
        return `${blockedState} border-2 border-dashed border-gray-300 bg-gray-50 text-gray-500 opacity-80 shadow-none`;
    }

    if (isExceptionSlot(slot)) {
        return `${blockedState} border-2 border-pup-maroon/70 border-l-4 bg-white text-pup-maroon-deep shadow-sm`;
    }

    return `${YEAR_LEVEL_CLASS[yearLevel(slot)]} ${blockedState}`;
}

function slotBlockStyle(slot: DailySlot, slots: DailySlot[]): Record<string, string> {
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



const props = defineProps<{
    rooms: RoomWithSlots[];
    allSlots: DailySlot[];
}>();

const emit = defineEmits<{
    'open-slot': [slot: DailySlot];
}>();
</script>

<template>
    <div class="relative z-0 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="min-w-[1120px]">
            <div class="flex border-b border-pup-maroon-deep bg-pup-maroon text-white">
                <div class="flex w-16 shrink-0 items-center justify-center border-r border-white/15 bg-pup-maroon-deep px-2 py-3 text-[10px] font-bold uppercase tracking-wider text-pup-gold-light">
                    Time
                </div>
                <div
                    v-for="room in rooms"
                    :key="room.id"
                    class="flex min-w-[118px] flex-1 items-center justify-center border-r border-white/10 px-1.5 py-3 last:border-r-0"
                >
                    <span class="font-mono text-xs font-bold tracking-wide text-white">
                        {{ room.code }}
                    </span>
                </div>
            </div>

            <div class="flex" :style="{ height: GRID_HEIGHT + 'px' }">
                <div class="relative w-16 shrink-0 border-r border-gray-200 bg-gray-50/80">
                    <div
                        v-for="hour in hours"
                        :key="hour"
                        class="absolute right-0 flex w-full items-center justify-end pr-2"
                        :style="{ top: ((hour - START_HOUR) * HOUR_HEIGHT - 8) + 'px' }"
                    >
                        <span class="text-[10px] font-medium leading-none text-gray-400">
                            {{ formatHour(hour) }}
                        </span>
                    </div>
                </div>

                <div
                    v-for="room in rooms"
                    :key="room.id"
                    class="group/room relative min-w-[118px] flex-1 border-r border-gray-100 last:border-r-0"
                >
                    <div
                        v-for="hour in hourLines"
                        :key="`line-${room.id}-${hour}`"
                        class="pointer-events-none absolute inset-x-0 border-t border-gray-100"
                        :style="{ top: ((hour - START_HOUR) * HOUR_HEIGHT) + 'px' }"
                    />
                    <div
                        v-for="hour in hourLines"
                        :key="`half-${room.id}-${hour}`"
                        class="pointer-events-none absolute inset-x-0 border-t border-dashed border-gray-50"
                        :style="{ top: ((hour - START_HOUR) * HOUR_HEIGHT + HOUR_HEIGHT / 2) + 'px' }"
                    />

                    <button
                        v-for="slot in room.slots.filter((item) => !isOverrideSlot(item))"
                        :key="slot.id"
                        type="button"
                        class="absolute inset-x-1 overflow-hidden rounded-md border px-1.5 py-1 text-left shadow-sm transition duration-150 hover:z-[60] hover:brightness-95 hover:shadow-md"
                        :class="slotBlockClass(slot, allSlots)"
                        :style="{
                            top: slotTop(slot.start_time) + 3 + 'px',
                            height: slotHeight(slot.start_time, slot.end_time) - 6 + 'px',
                            ...slotBlockStyle(slot, allSlots),
                        }"
                        @click="emit('open-slot', slot)"
                    >
                        <div class="flex items-start justify-between gap-1">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full border"
                                        :class="statusDotClass(slot)"
                                        :title="STATUS_LABEL[slot.status]"
                                    />
                                    <p class="line-clamp-2 text-[11px] font-bold leading-snug">
                                        {{ slot.subject_code }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <span
                                    v-if="blockingOverrideLabel(slot, allSlots)"
                                    class="rounded bg-pup-maroon/90 px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-white"
                                >
                                    Blocked
                                </span>
                                <span
                                    v-if="isExceptionSlot(slot) && !isCancelledSlot(slot)"
                                    class="rounded border border-pup-maroon/25 bg-pup-maroon-pale px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-pup-maroon"
                                >
                                    Exception
                                </span>
                                <span
                                    v-if="isCancelledSlot(slot)"
                                    class="rounded border border-gray-300 bg-white px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-gray-500"
                                >
                                    Cancelled
                                </span>
                            </div>
                        </div>

                        <p class="line-clamp-2 text-[10px] font-semibold leading-snug opacity-80">
                            {{ slot.subject_title }}
                        </p>
                        <p class="mt-0.5 text-[10px] opacity-65">{{ formatTimeRange(slot) }}</p>
                        <p class="truncate text-[10px] opacity-55">{{ slot.section }}</p>
                        <p
                            v-if="blockingOverrideLabel(slot, allSlots)"
                            class="mt-0.5 truncate text-[9px] font-semibold text-pup-maroon"
                        >
                            {{ blockingOverrideLabel(slot, allSlots) }}
                        </p>
                        <p v-if="slot.event_type === 'room_change'" class="mt-0.5 truncate text-[9px] font-semibold opacity-70">
                            From {{ slot.original_room_code }}
                        </p>
                    </button>

                    <div
                        v-for="slot in room.slots.filter((item) => isOverrideSlot(item))"
                        :key="slot.id"
                        class="pointer-events-none absolute inset-x-1 overflow-hidden rounded-md border px-1.5 py-1 text-left shadow-sm transition duration-150"
                        :class="slotBlockClass(slot, allSlots)"
                        :style="{
                            top: slotTop(slot.start_time) + 3 + 'px',
                            height: slotHeight(slot.start_time, slot.end_time) - 6 + 'px',
                            ...slotBlockStyle(slot, allSlots),
                        }"
                    >
                        <button
                            type="button"
                            class="pointer-events-auto absolute left-1/2 top-1.5 inline-flex max-w-[calc(100%-12px)] -translate-x-1/2 flex-col items-center rounded-md bg-white/90 px-2 py-1 text-center text-[10px] shadow-sm ring-1 ring-black/5 transition hover:bg-white hover:shadow-md"
                            @click.stop="emit('open-slot', slot)"
                        >
                            <span class="truncate text-[10px] font-bold uppercase tracking-wide">
                                {{ EVENT_TYPE_LABEL[slot.event_type] }}
                            </span>
                            <span class="text-[9px] font-medium opacity-75">
                                {{ formatTimeRange(slot) }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
