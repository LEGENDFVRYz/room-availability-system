<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, CalendarDays, Clock3, ClipboardPlus, RefreshCw, School } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import DailyFilters from './Components/DailyFilters.vue';
import DailyRequestClassModal from './Components/DailyRequestClassModal.vue';
import DailyRoomGrid from './Components/DailyRoomGrid.vue';
import DailySlotModal from './Components/DailySlotModal.vue';
import DailyTableView from './Components/DailyTableView.vue';
import type {
    ClassRequestPayload,
    CurrentTerm,
    DailySlot,
    DailySlotStatus,
    DailySlotType,
    Room,
    SharedProps,
    SlotActionPayload,
    ViewMode,
    YearLevel,
} from './Components/type';


// --- Helpers ---
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


// ---- Page Props and Templates ----
interface Props {
    currentTerm?: CurrentTerm | null;
    rooms?: Room[];
    daily_schedules?: DailySlot[];
    selected_date?: string;
}

const props = withDefaults(defineProps<Props>(), {
    rooms: () => [],
    daily_schedules: () => [],
    selected_date: '',
});

const page = usePage<SharedProps>();
const currentTerm = computed(() => page.props.currentTerm ?? props.currentTerm ?? null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Operations', href: '/admin/operations/daily' },
    { title: 'Daily Schedule', href: '/admin/operations/daily' },
];

const pageheader: PageHeader = {
    title: 'Daily Schedule',
    desc: 'Manage same-day class cancellations, room changes, special classes, and makeup classes.',
};

const manageTabs = [
    { label: 'Daily Schedule', href: '/admin/operations/daily', icon: CalendarDays },
    { label: 'Room Status', href: '/admin/operations/room-status', icon: School },
];

const selectedDate = ref(props.selected_date || todayIso());
const viewMode = ref<ViewMode>('room');
const selectedRoomIds = ref<number[]>([]);
const selectedType = ref<'all' | DailySlotType>('all');

// Client-side only clock state.
const currentDateTime = ref(new Date());
const lastUpdatedAt = ref(new Date());
let clockTimer: number | undefined;

const rooms = computed<Room[]>(() => props.rooms);
const sourceSlots = computed<DailySlot[]>(() => props.daily_schedules);
const localSlots = ref<DailySlot[]>([]);

watch(
    sourceSlots,
    (slots) => {
        localSlots.value = slots.map((slot) => ({ ...slot }));
    },
    { immediate: true },
);

const roomMap = computed(() => new Map(rooms.value.map((room) => [room.id, room])));
const roomCode = (roomId: number) => roomMap.value.get(roomId)?.code ?? `Room ${roomId}`;

const selectedDateLabel = computed(() => {
    const date = new Date(`${selectedDate.value}T00:00:00`);
    return date.toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
});

const currentTimeLabel = computed(() => {
    return currentDateTime.value.toLocaleTimeString('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
});

const lastUpdatedLabel = computed(() => {
    const diffInSeconds = Math.floor(
        (currentDateTime.value.getTime() - lastUpdatedAt.value.getTime()) / 1000,
    );

    if (diffInSeconds < 10) return 'Just now';
    if (diffInSeconds < 60) return `${diffInSeconds} sec ago`;

    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) return `${diffInMinutes} min ago`;

    const diffInHours = Math.floor(diffInMinutes / 60);
    return `${diffInHours} hr${diffInHours > 1 ? 's' : ''} ago`;
});

function refreshDailyOperations() {
    window.location.reload();
}

function goToSelectedDate() {
    router.get(
        '/admin/operations/daily',
        { selected_date: selectedDate.value },
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
        },
    );
}

const filteredSlots = computed(() => {
    return localSlots.value
        .filter((slot) => !slot.event_date || slot.event_date === selectedDate.value)
        .filter((slot) => selectedRoomIds.value.length === 0 || selectedRoomIds.value.includes(slot.room_id))
        .filter((slot) => selectedType.value === 'all' || slot.event_type === selectedType.value)
        .sort((a, b) => a.start_time.localeCompare(b.start_time) || roomCode(a.room_id).localeCompare(roomCode(b.room_id)));
});

const roomsForGrid = computed(() =>
    rooms.value
        .filter((room) => selectedRoomIds.value.length === 0 || selectedRoomIds.value.includes(room.id))
        .map((room) => ({
            ...room,
            slots: filteredSlots.value.filter((slot) => slot.room_id === room.id),
        })),
);

const exceptionCount = computed(() => filteredSlots.value.filter((slot) => slot.source === 'exception').length);
const cancelledCount = computed(() => filteredSlots.value.filter((slot) => ['cancelled', 'auto_cancelled'].includes(slot.status)).length);

const nowMinutes = computed(() => {
    if (selectedDate.value !== todayIso()) return null;

    const now = currentDateTime.value;
    return now.getHours() * 60 + now.getMinutes();
});

function isSlotHappeningNow(slot: DailySlot): boolean {
    if (nowMinutes.value === null) return false;
    if (['cancelled', 'auto_cancelled', 'completed'].includes(slot.status)) return false;

    return parseMinutes(slot.start_time) <= nowMinutes.value && nowMinutes.value < parseMinutes(slot.end_time);
}

const occupiedNowCount = computed(() => filteredSlots.value.filter(isSlotHappeningNow).length);
const freeRoomsNowCount = computed(() => {
    const visibleRooms = roomsForGrid.value;
    const occupiedRoomIds = new Set(filteredSlots.value.filter(isSlotHappeningNow).map((slot) => slot.room_id));

    return visibleRooms.filter((room) => !occupiedRoomIds.has(room.id)).length;
});
const upcomingSoonCount = computed(() => {
    if (nowMinutes.value === null) return 0;

    return filteredSlots.value.filter((slot) => {
        if (['cancelled', 'auto_cancelled', 'completed', 'ongoing'].includes(slot.status)) return false;

        const start = parseMinutes(slot.start_time);
        return start >= nowMinutes.value && start <= nowMinutes.value + 30;
    }).length;
});

const summaryStats = computed(() => ({
    freeRoomsNowCount: freeRoomsNowCount.value,
    occupiedNowCount: occupiedNowCount.value,
    upcomingSoonCount: upcomingSoonCount.value,
    exceptionCount: exceptionCount.value,
    cancelledCount: cancelledCount.value,
}));

const tableTimeGroups = computed(() => {
    const groups = new Map<string, DailySlot[]>();

    filteredSlots.value.forEach((slot) => {
        const key = `${slot.start_time}-${slot.end_time}`;
        groups.set(key, [...(groups.get(key) ?? []), slot]);
    });

    return Array.from(groups.entries()).map(([key, slots]) => ({
        key,
        label: formatTimeRange(slots[0]),
        slots,
    }));
});

const selectedSlot = ref<DailySlot | null>(null);
const showClassModal = ref(false);

function openClassModal() {
    showClassModal.value = true;
}

function closeClassModal() {
    showClassModal.value = false;
}

function saveClassPreview(payload: ClassRequestPayload) {
    if (!payload.room_id || !payload.subject_code || !payload.subject_title || !payload.section) return;

    localSlots.value.push({
        id: Date.now(),
        schedule_id: null,
        exception_id: Date.now(),
        room_id: payload.room_id,
        event_date: selectedDate.value,
        source: 'exception',
        event_type: payload.event_type,
        status: 'pending',
        subject_code: payload.subject_code,
        subject_title: payload.subject_title,
        section: payload.section,
        instructor_name: payload.instructor_name || null,
        start_time: payload.start_time,
        end_time: payload.end_time,
        reason: payload.reason || null,
    });

    closeClassModal();
}

function openSlot(slot: DailySlot) {
    selectedSlot.value = { ...slot };
}

function closeSlot() {
    selectedSlot.value = null;
}

function applyActionPreview(payload: SlotActionPayload) {
    const index = localSlots.value.findIndex((slot) => slot.id === payload.slot.id);
    if (index === -1) return;

    const current = { ...localSlots.value[index] };

    if (payload.action === 'cancel') {
        current.source = 'exception';
        current.event_type = 'cancellation';
        current.status = 'cancelled';
        current.reason = payload.reason || current.reason || 'Cancelled by admin.';
    }

    if (payload.action === 'change-room' && payload.room_id && payload.room_id !== current.room_id) {
        current.source = 'exception';
        current.event_type = 'room_change';
        current.status = 'pending';
        current.original_room_id = current.original_room_id ?? current.room_id;
        current.original_room_code = current.original_room_code ?? roomCode(current.room_id);
        current.room_id = payload.room_id;
        current.reason = payload.reason || current.reason || 'Room changed by admin.';
    }

    if (payload.action === 'start') {
        current.status = 'ongoing';
    }

    if (payload.action === 'complete') {
        current.status = 'completed';
    }

    localSlots.value.splice(index, 1, current);
    selectedSlot.value = { ...current };
}

onMounted(() => {
    clockTimer = window.setInterval(() => {
        currentDateTime.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    if (clockTimer) {
        window.clearInterval(clockTimer);
    }
});
</script>

<template>
    <Head title="Daily Schedule" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <PillTabs :tabs="manageTabs" />

                <button
                    type="button"
                    @click="openClassModal"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-pup-maroon/15 bg-white px-3.5 py-2 text-sm font-medium text-pup-maroon shadow-sm transition hover:bg-pup-maroon-pale"
                >
                    <Plus class="h-4 w-4" />
                    Request Class
                </button>
            </div>

            <div
                v-if="!currentTerm"
                class="flex items-start gap-3 rounded-xl border border-pup-gold/40 bg-pup-gold-pale/70 px-4 py-3 text-sm text-pup-maroon-dark"
            >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-pup-gold-dark" />
                <div>
                    <p class="font-semibold">No active academic term is set.</p>
                    <p class="text-pup-maroon-dark/80">
                        Daily operations still open for preview, but backend schedule data should be tied to the active term once connected.
                    </p>
                </div>
            </div>

            <div
                v-else
                class="overflow-hidden rounded-2xl border border-pup-gold/40 bg-gradient-to-r from-pup-maroon via-pup-maroon to-pup-maroon-deep text-white shadow-lg shadow-pup-maroon/10"
            >
                <div class="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/12 ring-1 ring-white/15">
                            <CalendarDays class="h-5 w-5 text-pup-gold-light" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-pup-gold-light">Daily operations board</p>
                            <p class="mt-1 text-lg font-semibold leading-tight">
                                Operations: <span class="text-pup-gold-light">{{ selectedDateLabel }}</span>
                            </p>
                            <p class="mt-1 text-sm text-white/70">
                                Room View shows the day by room and time. Table View is for detailed checking.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <div class="flex items-center gap-2 rounded-xl bg-white/10 px-3 py-2 font-medium ring-1 ring-white/15">
                            <RefreshCw class="h-4 w-4 text-pup-gold-light" />
                            <span class="text-white/75">Last update:</span>
                            <b>{{ lastUpdatedLabel }}</b>
                        </div>

                        <button
                            type="button"
                            @click="refreshDailyOperations"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-xs font-bold text-pup-maroon shadow-sm transition hover:bg-pup-gold-pale"
                        >
                            <RefreshCw class="h-3.5 w-3.5" />
                            Refresh
                        </button>

                        <div class="flex items-center gap-2 rounded-xl bg-pup-gold px-3 py-2 font-semibold text-pup-maroon-deep shadow-sm">
                            <Clock3 class="h-4 w-4" />
                            <span>Current time:</span>
                            <b>{{ currentTimeLabel }}</b>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-green-100 bg-green-50/70 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-600">Available Now</p>
                    <p class="mt-2 text-2xl font-bold text-green-800">{{ summaryStats.freeRoomsNowCount }}</p>
                    <p class="text-xs text-green-600">selected rooms without active class</p>
                </div>
                <div class="rounded-xl border border-red-100 bg-red-50/70 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-red-500">Occupied Now</p>
                    <p class="mt-2 text-2xl font-bold text-red-800">{{ summaryStats.occupiedNowCount }}</p>
                    <p class="text-xs text-red-600">classes currently using rooms</p>
                </div>
                <div class="rounded-xl border border-amber-100 bg-amber-50/70 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Upcoming</p>
                    <p class="mt-2 text-2xl font-bold text-amber-800">{{ summaryStats.upcomingSoonCount }}</p>
                    <p class="text-xs text-amber-600">classes within the next 30 minutes</p>
                </div>
                <div class="rounded-xl border border-orange-100 bg-orange-50/70 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-orange-600">Exceptions / Cancelled</p>
                    <p class="mt-2 text-2xl font-bold text-orange-800">{{ summaryStats.exceptionCount }} / {{ summaryStats.cancelledCount }}</p>
                    <p class="text-xs text-orange-600">changes from baseline / freed slots</p>
                </div>
            </div>

            <DailyFilters
                v-model:selected-date="selectedDate"
                v-model:selected-room-ids="selectedRoomIds"
                v-model:selected-type="selectedType"
                v-model:view-mode="viewMode"
                :rooms="rooms"
                @date-change="goToSelectedDate"
            />

            <DailyRoomGrid
                v-if="viewMode === 'room'"
                :rooms="roomsForGrid"
                :all-slots="filteredSlots"
                @open-slot="openSlot"
            />

            <DailyTableView
                v-else
                :groups="tableTimeGroups"
                :rooms="rooms"
                :all-slots="filteredSlots"
                @open-slot="openSlot"
            />
        </div>
    </AppLayout>

    <DailySlotModal
        :slot="selectedSlot"
        :rooms="rooms"
        :all-slots="filteredSlots"
        @close="closeSlot"
        @apply-action="applyActionPreview"
    />

    <DailyRequestClassModal
        :show="showClassModal"
        :rooms="rooms"
        :selected-date-label="selectedDateLabel"
        @close="closeClassModal"
        @save="saveClassPreview"
    />
</template>
