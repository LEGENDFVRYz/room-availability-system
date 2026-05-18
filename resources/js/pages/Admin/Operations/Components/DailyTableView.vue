<script setup lang="ts">
import { Eye, ListFilter } from 'lucide-vue-next';
import { computed } from 'vue';
interface CurrentTerm {
    school_year: string;
    semester_label: string;
    year_start: number;
    semester: number;
}

interface SharedProps {
    currentTerm?: CurrentTerm | null;
}

interface Room {
    id: number;
    code: string;
    name: string;
    type?: string;
}

type DailySlotSource = 'schedule' | 'exception' | 'override';
type DailySlotType =
    | 'regular'
    | 'cancellation'
    | 'room_change'
    | 'special_class'
    | 'makeup_class'
    | 'maintenance'
    | 'unavailable'
    | 'reserved';
type DailySlotStatus =
    | 'scheduled'
    | 'pending'
    | 'ongoing'
    | 'completed'
    | 'cancelled'
    | 'auto_cancelled'
    | 'maintenance'
    | 'unavailable'
    | 'reserved';

interface DailySlot {
    id: number | string;
    schedule_id?: number | null;
    exception_id?: number | null;
    override_id?: number | null;
    room_id: number;
    original_room_id?: number | null;
    original_room_code?: string | null;
    event_date: string;
    source: DailySlotSource;
    event_type: DailySlotType;
    status: DailySlotStatus;
    subject_code: string;
    subject_title: string;
    section: string;
    instructor_name?: string | null;
    start_time: string;
    end_time: string;
    reason?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
}

interface RoomWithSlots extends Room {
    slots: DailySlot[];
}

interface SummaryStats {
    freeRoomsNowCount: number;
    occupiedNowCount: number;
    upcomingSoonCount: number;
    exceptionCount: number;
    cancelledCount: number;
}

interface TimeGroup {
    key: string;
    label: string;
    slots: DailySlot[];
}

type ViewMode = 'room' | 'table';
type SlotAction = 'cancel' | 'change-room' | 'start' | 'complete';

interface ClassRequestPayload {
    event_type: 'special_class' | 'makeup_class';
    room_id: number | null;
    subject_code: string;
    subject_title: string;
    section: string;
    instructor_name: string;
    start_time: string;
    end_time: string;
    reason: string;
}

interface SlotActionPayload {
    slot: DailySlot;
    action: SlotAction;
    reason: string;
    room_id: number | null;
}

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
    scheduled: 'Scheduled',
    pending: 'Pending',
    ongoing: 'Ongoing',
    completed: 'Completed',
    cancelled: 'Cancelled',
    auto_cancelled: 'Auto-cancelled',
    maintenance: 'Maintenance',
    unavailable: 'Unavailable',
    reserved: 'Reserved',
};

const EXCEPTION_BADGE_CLASS = 'border border-pup-gold/50 bg-pup-gold-pale text-pup-maroon';

const EVENT_BADGE: Record<DailySlotType, string> = {
    regular: 'bg-sky-100 text-sky-700',
    cancellation: EXCEPTION_BADGE_CLASS,
    room_change: EXCEPTION_BADGE_CLASS,
    special_class: EXCEPTION_BADGE_CLASS,
    makeup_class: EXCEPTION_BADGE_CLASS,
    maintenance: 'bg-status-maintenance-bg text-status-maintenance',
    unavailable: 'bg-pup-gray-200 text-pup-gray-800',
    reserved: 'bg-status-reserved-bg text-status-reserved',
};

const STATUS_BADGE: Record<DailySlotStatus, string> = {
    scheduled: 'bg-blue-50 text-blue-700',
    pending: 'bg-amber-50 text-amber-700',
    ongoing: 'bg-green-50 text-green-700',
    completed: 'bg-gray-100 text-gray-600',
    cancelled: 'bg-slate-100 text-slate-600',
    auto_cancelled: 'bg-rose-50 text-rose-700',
    maintenance: 'bg-status-maintenance-bg text-status-maintenance',
    unavailable: 'bg-pup-gray-200 text-pup-gray-800',
    reserved: 'bg-status-reserved-bg text-status-reserved',
};

type YearLevel = '1' | '2' | '3' | '4' | 'unknown';

const YEAR_LEVEL_CLASS: Record<YearLevel, string> = {
    '1': 'border-sky-300 bg-sky-50 text-sky-950',
    '2': 'border-emerald-300 bg-emerald-50 text-emerald-950',
    '3': 'border-amber-300 bg-amber-50 text-amber-950',
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

function overrideHasAffectedClass(slot: DailySlot, slots: DailySlot[]): boolean {
    if (!isOverrideSlot(slot)) return false;

    return slots.some((candidate) => {
        return isClassSlot(candidate)
            && candidate.room_id === slot.room_id
            && !['cancelled', 'auto_cancelled'].includes(candidate.status)
            && slotsOverlap(slot, candidate);
    });
}

function slotBlockClass(slot: DailySlot, slots: DailySlot[]): string {
    if (isOverrideSlot(slot)) {
        const overlapState = overrideHasAffectedClass(slot, slots)
            ? 'z-20 border-dashed opacity-75 shadow-none'
            : 'z-30';

        const overrideClass: Record<string, string> = {
            maintenance: 'border-status-maintenance bg-status-maintenance-bg text-pup-gray-800',
            unavailable: 'border-pup-gray-600 bg-pup-gray-200 text-pup-gray-800',
            reserved: 'border-status-reserved-border bg-status-reserved-bg text-status-reserved',
        };

        return `${overrideClass[slot.event_type] ?? overrideClass.maintenance} ${overlapState}`;
    }

    const base = YEAR_LEVEL_CLASS[yearLevel(slot)];
    const blocker = blockingOverride(slot, slots);
    const exceptionState = isExceptionSlot(slot)
        ? 'border-2 border-pup-maroon/70 border-l-4 bg-white text-pup-maroon-deep shadow-sm'
        : '';

    if (['cancelled', 'auto_cancelled'].includes(slot.status)) {
        return `${isExceptionSlot(slot) ? exceptionState : base} z-30 opacity-60 grayscale`;
    }

    if (blocker) {
        return `${isExceptionSlot(slot) ? exceptionState : base} z-40 shadow-sm`;
    }

    if (slot.status === 'ongoing') {
        return `${isExceptionSlot(slot) ? exceptionState : base} z-30 ring-2 ring-green-400/60`;
    }

    return `${isExceptionSlot(slot) ? exceptionState : base} z-30`;
}

function slotBlockStyle(slot: DailySlot, slots: DailySlot[]): Record<string, string> {
    if (isOverrideSlot(slot) && overrideHasAffectedClass(slot, slots)) {
        return {
            backgroundImage:
                'repeating-linear-gradient(135deg, rgba(74, 11, 24, 0.14) 0px, rgba(74, 11, 24, 0.14) 3px, transparent 3px, transparent 9px)',
        };
    }

    return {};
}



const props = defineProps<{
    groups: TimeGroup[];
    rooms: Room[];
    allSlots: DailySlot[];
}>();

const emit = defineEmits<{
    'open-slot': [slot: DailySlot];
}>();

const roomMap = computed(() => new Map(props.rooms.map((room) => [room.id, room])));
const roomCode = (roomId: number) => roomMap.value.get(roomId)?.code ?? `Room ${roomId}`;
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">
            <ListFilter class="h-4 w-4 text-pup-maroon" />
            Detailed daily list
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
                            class="hover:bg-pup-maroon-pale/30"
                        >
                            <td
                                v-if="index === 0"
                                :rowspan="group.slots.length"
                                class="whitespace-nowrap border-r border-gray-100 bg-gray-50/70 px-4 py-3 align-top font-semibold text-gray-700"
                            >
                                {{ group.label }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-800">{{ roomCode(slot.room_id) }}</div>
                                <div v-if="slot.original_room_code" class="text-xs text-orange-600">
                                    moved from {{ slot.original_room_code }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ slot.subject_code }} · {{ slot.subject_title }}</div>
                                <div class="text-xs text-gray-500">{{ slot.section }}</div>
                                <div v-if="blockingOverrideDetails(slot, allSlots)" class="mt-1 inline-flex rounded-full bg-pup-maroon-pale px-2 py-0.5 text-[11px] font-bold text-pup-maroon">
                                    Room status: {{ blockingOverrideDetails(slot, allSlots) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ slot.instructor_name || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="EVENT_BADGE[slot.event_type]">
                                    {{ EVENT_TYPE_LABEL[slot.event_type] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="STATUS_BADGE[slot.status]">
                                    {{ STATUS_LABEL[slot.status] }}
                                </span>
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
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                            No schedule items match the selected filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
