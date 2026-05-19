<script setup lang="ts">
import { Eye, ListFilter } from 'lucide-vue-next';
import { computed } from 'vue';
import type { DailySlot, DailySlotStatus, DailySlotType, Room, TimeGroup, YearLevel } from './type';

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
    return slot.source === 'exception' || ['cancellation', 'room_change', 'special_class', 'makeup_class'].includes(slot.event_type);
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

function slotsOverlap(first: DailySlot, second: DailySlot): boolean {
    return parseMinutes(first.start_time) < parseMinutes(second.end_time) && parseMinutes(first.end_time) > parseMinutes(second.start_time);
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

function slotBlockClass(slot: DailySlot, slots: DailySlot[]): string {
    if (isOverrideSlot(slot)) {
        const overlapState = overrideHasAffectedClass(slot, slots) ? 'z-20 border-dashed opacity-80 shadow-none' : 'z-30';

        const overrideClass: Record<string, string> = {
            maintenance: 'border-status-maintenance bg-status-maintenance-bg text-pup-gray-800',
            unavailable: 'border-pup-gray-600 bg-pup-gray-200 text-pup-gray-800',
            reserved: 'border-status-reserved-border bg-status-reserved-bg text-status-reserved',
        };

        return `${overrideClass[slot.event_type] ?? overrideClass.maintenance} ${overlapState}`;
    }

    if (isCancelledSlot(slot)) {
        return 'z-30 border-2 border-dashed border-gray-300 bg-gray-50 text-gray-500 opacity-80 shadow-none';
    }

    const exceptionState = isExceptionSlot(slot) ? 'z-30 border-2 border-pup-maroon/70 border-l-4 bg-white text-pup-maroon-deep shadow-sm' : '';

    if (isExceptionSlot(slot)) {
        return exceptionState;
    }

    return `${YEAR_LEVEL_CLASS[yearLevel(slot)]} z-30`;
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
    groups: TimeGroup[];
    rooms: Room[];
    allSlots: DailySlot[];
    selectedDate: string;
    currentDateTime: Date | string;
}>();

const emit = defineEmits<{
    'open-slot': [slot: DailySlot];
}>();

const roomMap = computed(() => new Map(props.rooms.map((room) => [room.id, room])));
const roomCode = (roomId: number) => roomMap.value.get(roomId)?.code ?? `Room ${roomId}`;

const activeNow = computed(() => {
    const now = props.currentDateTime instanceof Date ? props.currentDateTime : new Date(props.currentDateTime);

    if (Number.isNaN(now.getTime()) || props.selectedDate !== todayIso(now)) {
        return null;
    }

    return now.getHours() * 60 + now.getMinutes();
});

const currentTimeLabel = computed(() => (activeNow.value === null ? '' : formatMinutes(activeNow.value)));

function isSlotCurrent(slot: DailySlot): boolean {
    if (activeNow.value === null) return false;

    return parseMinutes(slot.start_time) <= activeNow.value && activeNow.value < parseMinutes(slot.end_time);
}

function isGroupCurrent(group: TimeGroup): boolean {
    return group.slots.some(isSlotCurrent);
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">
            <ListFilter class="h-4 w-4 text-pup-maroon" />
            Detailed daily list
        </div>

        <div
            v-if="activeNow !== null"
            class="flex items-center justify-between gap-3 border-b border-pup-gold/30 bg-pup-gold-pale/40 px-4 py-2 text-xs font-semibold text-pup-maroon-deep"
        >
            <span>Current time: {{ currentTimeLabel }}</span>
            <span class="text-pup-maroon/70">Rows overlapping the current time are highlighted.</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-pup-maroon text-xs uppercase tracking-wide text-white">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Time</th>
                        <th class="px-4 py-3 text-left font-semibold">Room</th>
                        <th class="px-4 py-3 text-left font-semibold">Class</th>
                        <th class="px-4 py-3 text-left font-semibold">Instructor</th>
                        <th class="px-4 py-3 text-left font-semibold">Type</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <template v-for="group in groups" :key="group.key">
                        <tr
                            v-for="(slot, index) in group.slots"
                            :key="slot.id"
                            class="transition"
                            :class="isSlotCurrent(slot) ? 'bg-pup-gold-pale/60 ring-1 ring-inset ring-pup-gold/40' : 'hover:bg-pup-maroon-pale/30'"
                        >
                            <td
                                v-if="index === 0"
                                :rowspan="group.slots.length"
                                class="whitespace-nowrap border-r border-gray-100 px-4 py-3 align-top font-semibold text-gray-700"
                                :class="isGroupCurrent(group) ? 'bg-pup-gold-pale text-pup-maroon-deep' : 'bg-gray-50/70'"
                            >
                                <div class="flex items-center gap-2">
                                    <span>{{ group.label }}</span>
                                    <span
                                        v-if="isGroupCurrent(group)"
                                        class="rounded-full bg-pup-maroon px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                                    >
                                        Now
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-800">
                                    {{ roomCode(slot.room_id) }}
                                </div>
                                <div v-if="slot.original_room_code" class="text-xs text-orange-600">moved from {{ slot.original_room_code }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ slot.subject_code }} · {{ slot.subject_title }}</div>
                                <div class="text-xs text-gray-500">{{ slot.section }}</div>
                                <div
                                    v-if="blockingOverrideDetails(slot, allSlots)"
                                    class="mt-1 inline-flex rounded-full bg-pup-maroon-pale px-2 py-0.5 text-[11px] font-bold text-pup-maroon"
                                >
                                    Room status: {{ blockingOverrideDetails(slot, allSlots) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ slot.instructor_name || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="EVENT_BADGE[slot.event_type]">
                                    {{ EVENT_TYPE_LABEL[slot.event_type] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700">
                                    <span v-if="slot.source !== 'override'" class="h-2.5 w-2.5 rounded-full border" :class="statusDotClass(slot)" />
                                    <span class="rounded-full px-2.5 py-1" :class="STATUS_BADGE[slot.status]">
                                        {{ STATUS_LABEL[slot.status] }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    @click="emit('open-slot', slot)"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-pup-maroon hover:text-pup-maroon"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    View
                                </button>
                            </td>
                        </tr>
                    </template>

                    <tr v-if="groups.length === 0">
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No schedule items match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
