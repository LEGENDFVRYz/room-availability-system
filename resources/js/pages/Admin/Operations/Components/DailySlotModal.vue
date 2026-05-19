<script setup lang="ts">
import { ArrowRightLeft, Ban, CheckCircle2, Play, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { DailySlot, DailySlotStatus, DailySlotType, Room, SlotAction, SlotActionPayload, YearLevel } from './type';

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
            ? 'z-20 border-dashed opacity-80 shadow-none'
            : 'z-30';

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

    const exceptionState = isExceptionSlot(slot)
        ? 'z-30 border-2 border-pup-maroon/70 border-l-4 bg-white text-pup-maroon-deep shadow-sm'
        : '';

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
    slot: DailySlot | null;
    rooms: Room[];
    allSlots: DailySlot[];
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    close: [];
    'apply-action': [payload: SlotActionPayload];
}>();

const activeAction = ref<SlotAction | null>(null);
const actionReason = ref('');
const changeRoomId = ref<number | null>(null);

const actionError = computed(() => {
    return props.errors?.room_id
        ?? props.errors?.start_time
        ?? props.errors?.end_time
        ?? props.errors?.schedule_id
        ?? props.errors?.exception_id
        ?? '';
});

const roomMap = computed(() => new Map(props.rooms.map((room) => [room.id, room])));
const roomLabel = (roomId: number) => {
    const room = roomMap.value.get(roomId);
    return room ? `${room.code} · ${room.name}` : `Room #${roomId}`;
};

watch(
    () => props.slot,
    (slot) => {
        activeAction.value = null;
        actionReason.value = '';
        changeRoomId.value = slot?.room_id ?? null;
    },
);

function openAction(action: SlotAction) {
    activeAction.value = action;
    actionReason.value = '';
    changeRoomId.value = props.slot?.room_id ?? null;
}

function applyAction() {
    if (!props.slot || !activeAction.value) return;

    emit('apply-action', {
        slot: props.slot,
        action: activeAction.value,
        reason: actionReason.value,
        room_id: changeRoomId.value,
    });

    // Keep the action panel open until the parent request succeeds.
    // If validation fails, the error panel stays visible inside this modal.
}
</script>

<template>
    <Teleport to="body">
        <div v-if="slot" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4">
            <div class="max-h-[calc(100vh-2rem)] w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-pup-maroon">Schedule item</p>
                    <h3 class="mt-1 text-xl font-bold text-gray-900">{{ slot.subject_code }} · {{ slot.subject_title }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ slot.section || 'Room status item' }} · {{ formatTimeRange(slot) }}</p>
                </div>
                <button type="button" @click="emit('close')" class="rounded-full p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
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
                            <span
                                v-if="slot.source !== 'override'"
                                class="h-2.5 w-2.5 rounded-full border"
                                :class="statusDotClass(slot)"
                            />
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

                <div v-if="slot.reason" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p class="font-semibold">Reason / Note</p>
                    <p class="mt-1">{{ slot.reason }}</p>
                </div>

                <div v-if="!activeAction" class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                    <button
                        v-if="slot.source !== 'override' && !['cancelled', 'auto_cancelled', 'completed'].includes(slot.status)"
                        type="button"
                        @click="openAction('cancel')"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                    >
                        <Ban class="h-4 w-4" />
                        Cancel
                    </button>
                    <button
                        v-if="slot.source !== 'override' && !['cancelled', 'auto_cancelled', 'completed'].includes(slot.status)"
                        type="button"
                        @click="openAction('change-room')"
                        class="inline-flex items-center gap-2 rounded-lg border border-amber-200 px-3 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-50"
                    >
                        <ArrowRightLeft class="h-4 w-4" />
                        Change Room
                    </button>
                    <button
                        v-if="slot.source !== 'override' && ['scheduled', 'pending'].includes(slot.status)"
                        type="button"
                        @click="openAction('start')"
                        class="inline-flex items-center gap-2 rounded-lg border border-green-200 px-3 py-2 text-sm font-semibold text-green-700 transition hover:bg-green-50"
                    >
                        <Play class="h-4 w-4" />
                        Mark Started
                    </button>
                    <button
                        v-if="slot.source !== 'override' && slot.status === 'ongoing'"
                        type="button"
                        @click="openAction('complete')"
                        class="inline-flex items-center gap-2 rounded-lg bg-pup-maroon px-3 py-2 text-sm font-semibold text-white transition hover:bg-pup-maroon-deep"
                    >
                        <CheckCircle2 class="h-4 w-4" />
                        Complete
                    </button>
                </div>

                <div v-else class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <h4 class="font-semibold text-gray-900">
                        <template v-if="activeAction === 'cancel'">Cancel this class?</template>
                        <template v-if="activeAction === 'change-room'">Change room for this date?</template>
                        <template v-if="activeAction === 'start'">Mark this class as started?</template>
                        <template v-if="activeAction === 'complete'">Mark this class as completed?</template>
                    </h4>
                    <p class="mt-1 text-sm text-gray-500">
                        This action will update the Daily Operations records and refresh the board.
                    </p>

                    <div v-if="actionError" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700">
                        <p class="font-semibold">Action cannot be completed.</p>
                        <p class="mt-1 leading-relaxed">{{ actionError }}</p>
                    </div>

                    <label v-if="activeAction === 'change-room'" class="mt-4 flex flex-col gap-1 text-sm font-medium text-gray-600">
                        New room
                        <select v-model="changeRoomId" class="h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-800 shadow-sm outline-none transition focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15">
                            <option v-for="room in rooms" :key="room.id" :value="room.id">
                                {{ room.code }} · {{ room.name }}
                            </option>
                        </select>
                    </label>

                    <label v-if="activeAction === 'cancel' || activeAction === 'change-room'" class="mt-4 flex flex-col gap-1 text-sm font-medium text-gray-600">
                        Reason
                        <textarea
                            v-model="actionReason"
                            rows="3"
                            class="min-h-[90px] resize-none rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-800 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15"
                            placeholder="Optional note for this daily operation"
                        />
                    </label>

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" @click="activeAction = null" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-white">
                            Back
                        </button>
                        <button type="button" @click="applyAction" class="rounded-lg bg-pup-maroon px-3 py-2 text-sm font-semibold text-white hover:bg-pup-maroon-deep">
                            Apply
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </Teleport>
</template>
