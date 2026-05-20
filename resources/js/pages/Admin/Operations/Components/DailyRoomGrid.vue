<script setup lang="ts">
import { computed } from 'vue';
import type { DailySlot, DailySlotStatus, DailySlotType, RoomWithSlots, YearLevel } from './type';

const START_HOUR = 7;
const END_HOUR = 21;
const HOUR_HEIGHT = 76;
const TIME_COLUMN_WIDTH = 64;
const ROOM_COLUMN_WIDTH = 118;
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

function todayIso(date = new Date()): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
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

function formatMinutes(minutes: number): string {
    const hour = Math.floor(minutes / 60);
    const minute = minutes % 60;

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
    return slot.source === 'exception' || ['cancellation', 'room_change', 'special_class', 'makeup_class'].includes(slot.event_type);
}

function slotsOverlap(first: DailySlot, second: DailySlot): boolean {
    return parseMinutes(first.start_time) < parseMinutes(effectiveEndTime(second))
        && parseMinutes(effectiveEndTime(first)) > parseMinutes(second.start_time);
}

function overlappingOverrides(slot: DailySlot, slots: DailySlot[]): DailySlot[] {
    if (isOverrideSlot(slot)) return [];

    return slots.filter((candidate) => {
        return isOverrideSlot(candidate) && candidate.room_id === slot.room_id && slotsOverlap(slot, candidate);
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
        return (
            isClassSlot(candidate) &&
            candidate.room_id === slot.room_id &&
            !['cancelled', 'auto_cancelled'].includes(candidate.status) &&
            slotsOverlap(slot, candidate)
        );
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
            maintenance: 'border-status-maintenance bg-status-maintenance-bg text-pup-gray-800 opacity-70',
            unavailable: 'border-pup-gray-600 bg-pup-gray-200 text-pup-gray-800 opacity-70',
            reserved:    'border-status-reserved-border bg-status-reserved-bg text-status-reserved opacity-70',
        };

        return `${overrideClass[slot.event_type] ?? overrideClass.maintenance} ${overlapState}`;
    }

    const blockedState = isBlockedByOverride(slot, slots)
        ? 'z-20 opacity-45 group-hover/room:z-40 group-hover/room:opacity-100 group-hover/room:shadow-md'
        : 'z-30';

    if (isCancelledSlot(slot)) {
        return `${blockedState} border-2 border-dashed border-gray-300 bg-gray-50 text-gray-500 opacity-50`;
    }

    if (isExceptionSlot(slot)) {
        return `${blockedState} border-2 border-pup-maroon/70 bg-white text-pup-maroon-deep shadow-sm`;
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
                'transparent 3px, transparent 9px)',
        };
    }

    return {};
}

const props = defineProps<{
    rooms: RoomWithSlots[];
    allSlots: DailySlot[];
    selectedDate: string;
    currentDateTime: Date | string;
}>();

const gridMinWidth = computed(() => `${TIME_COLUMN_WIDTH + props.rooms.length * ROOM_COLUMN_WIDTH}px`);

const activeNow = computed(() => {
    const now = props.currentDateTime instanceof Date ? props.currentDateTime : new Date(props.currentDateTime);

    if (Number.isNaN(now.getTime()) || props.selectedDate !== todayIso(now)) {
        return null;
    }

    return now.getHours() * 60 + now.getMinutes();
});

const currentTimeTop = computed(() => {
    if (activeNow.value === null) return null;

    return ((clampMinutes(activeNow.value) - visibleStartMinutes) / 60) * HOUR_HEIGHT;
});

const showCurrentTimeMarker = computed(() => {
    return activeNow.value !== null && activeNow.value >= visibleStartMinutes && activeNow.value <= visibleEndMinutes;
});

const currentTimeLabel = computed(() => (activeNow.value === null ? '' : formatMinutes(activeNow.value)));

const emit = defineEmits<{
    'open-slot': [slot: DailySlot];
}>();
</script>

<template>
    <div class="relative z-0 rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto overflow-y-hidden rounded-md">
            <div class="w-full" :style="{ minWidth: gridMinWidth }">
                <div class="flex border-b border-pup-maroon-deep bg-pup-maroon text-white">
                    <div
                        class="sticky left-0 z-30 flex w-16 shrink-0 items-center justify-center border-r border-white/15 bg-pup-maroon-deep px-2 py-3 text-[10px] font-bold uppercase tracking-wider text-pup-gold-light shadow-[8px_0_12px_-12px_rgba(0,0,0,0.6)]"
                    >
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

                <div class="relative flex" :style="{ minHeight: GRID_HEIGHT + 'px' }">
                    <div class="sticky left-0 z-[80] w-16 shrink-0 border-r border-gray-200 bg-gray-50/95 shadow-[8px_0_12px_-12px_rgba(0,0,0,0.35)]">
                        <div
                            v-for="hour in hours"
                            :key="hour"
                            class="absolute right-0 flex w-full items-center justify-end pr-2"
                            :style="{ top: (hour - START_HOUR) * HOUR_HEIGHT - 8 + 'px' }"
                        >
                            <span class="text-[10px] font-medium leading-none text-gray-400">
                                {{ formatHour(hour) }}
                            </span>
                        </div>

                        <div
                            v-if="showCurrentTimeMarker"
                            class="pointer-events-none absolute inset-x-0 z-40"
                            :style="{ top: currentTimeTop + 'px' }"
                        >
                            <!-- <div class="absolute right-0 top-1/2 h-0.5 w-2 -translate-y-1/2 bg-pup-maroon" /> -->
                            <span
                                class="absolute right-1 flex w-[54px] -translate-y-1/2 flex-col items-center rounded-full bg-pup-maroon px-1 py-1 text-[10px] font-bold uppercase leading-tight tracking-wide text-white shadow-sm ring-2 ring-white"
                            >
                                <span>{{ currentTimeLabel }}</span>
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="showCurrentTimeMarker"
                        class="pointer-events-none absolute right-0 z-50 border-t-2 border-pup-maroon/80"
                        :style="{
                            top: currentTimeTop + 'px',
                            left: TIME_COLUMN_WIDTH + 'px',
                        }"
                    />

                    <div v-for="room in rooms" :key="room.id" class="group/room relative min-w-[118px] flex-1 border-r border-gray-100 last:border-r-0">
                        <div
                            v-for="hour in hourLines"
                            :key="`line-${room.id}-${hour}`"
                            class="pointer-events-none absolute inset-x-0 border-t border-gray-100"
                            :style="{ top: (hour - START_HOUR) * HOUR_HEIGHT + 'px' }"
                        />
                        <div
                            v-for="hour in hourLines"
                            :key="`half-${room.id}-${hour}`"
                            class="pointer-events-none absolute inset-x-0 border-t border-dashed border-gray-50"
                            :style="{
                                top: (hour - START_HOUR) * HOUR_HEIGHT + HOUR_HEIGHT / 2 + 'px',
                            }"
                        />

                        <button
                            v-for="slot in room.slots.filter((item) => !isOverrideSlot(item))"
                            :key="slot.id"
                            type="button"
                            class="absolute inset-x-1 overflow-hidden rounded-md border px-1.5 py-1 text-left shadow-sm transition duration-150 hover:z-[60] hover:brightness-95 hover:shadow-md"
                            :class="slotBlockClass(slot, allSlots)"
                            :style="{
                                top: slotTop(slot.start_time) + 3 + 'px',
                                height: slotHeight(slot.start_time, effectiveEndTime(slot)) - 6 + 'px',
                                ...slotBlockStyle(slot, allSlots),
                            }"
                            @click="emit('open-slot', slot)"
                        >
                            <!-- Special Tags -->
                            <div class="flex flex-col items-end gap-1 mb-2">
                                <span
                                    v-if="blockingOverrideLabel(slot, allSlots)"
                                    class="rounded bg-pup-maroon/90 px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-white w-full text-center"
                                >
                                    Blocked
                                </span>
                                <span
                                    v-if="isExceptionSlot(slot) && !isCancelledSlot(slot)"
                                    class="rounded border border-pup-maroon/25 bg-pup-maroon-pale px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-pup-maroon w-full text-center"
                                >
                                    Exception
                                </span>
                                <span
                                    v-if="isCancelledSlot(slot)"
                                    class="rounded border border-gray-300 bg-white px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-gray-500 w-full text-center"
                                >
                                    Cancelled
                                </span>
                            </div>
                            
                            <div class="flex flex-1 items-center justify-between gap-2 w-full min-w-0">
                                <div class="min-w-0">
                                    <p class="line-clamp-2 text-[11px] font-bold leading-snug text-gray-900">
                                        {{ slot.subject_code }}
                                    </p>
                                </div>
                                
                                <span 
                                    class="h-2 w-2 shrink-0 rounded-full border" 
                                    :class="statusDotClass(slot)" 
                                    :title="STATUS_LABEL[slot.status]" 
                                />
                            </div>

                            <p class="line-clamp-2 text-[10px] font-semibold leading-snug opacity-80">
                                {{ slot.subject_title }}
                            </p>
                            <p class="mt-0.5 text-[10px] opacity-65">
                                {{ formatTimeRange(slot) }}
                            </p>
                            <!-- <p v-if="isTrimmedByActualEnd(slot)" class="truncate text-[9px] font-semibold text-green-700">
                                Ended early · was until {{ formatOriginalEnd(slot) }}
                            </p> -->
                            <p class="truncate text-[10px] opacity-55">{{ slot.section }}</p>
                            <p v-if="blockingOverrideLabel(slot, allSlots)" class="mt-0.5 truncate text-[9px] font-semibold text-pup-maroon">
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
    </div>
</template>
