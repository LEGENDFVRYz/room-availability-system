<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle, ArrowRightLeft, Ban, CalendarDays, CheckCircle2, Clock3, ChevronDown, Eye, 
    LayoutGrid, ListFilter, Play, Plus, RefreshCw, School, Sparkles, Table2, X, 
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';


// --- Page Types ---
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
type DailySlotType = 'regular' | 'cancellation' | 'room_change' | 'special_class' | 'makeup_class' | 'maintenance' | 'unavailable' | 'reserved';
type DailySlotStatus = 'scheduled' | 'pending' | 'ongoing' | 'completed' | 'cancelled' | 'auto_cancelled' | 'maintenance' | 'unavailable' | 'reserved';

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


// --- Page Props and Template Setup ---
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


// --- Helpers ---
const todayIso = () => new Date().toISOString().slice(0, 10);
const selectedDate = ref(props.selected_date || todayIso());
const viewMode = ref<'room' | 'table'>('room');
const selectedRoomIds = ref<number[]>([]);
const selectedType = ref<'all' | DailySlotType>('all');
const isRoomFilterOpen = ref(false);

// Client-side only clock state.
const currentDateTime = ref(new Date());
const lastUpdatedAt = ref(new Date());
let clockTimer: number | undefined;

const START_HOUR = 7;
const END_HOUR = 21;
const HOUR_HEIGHT = 76;
const GRID_HEIGHT = (END_HOUR - START_HOUR) * HOUR_HEIGHT;
const hours = Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, index) => START_HOUR + index);
const hourLines = hours.slice(0, -1);

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

const roomLabel = (roomId: number) => {
    const room = roomMap.value.get(roomId);
    return room ? `${room.code} · ${room.name}` : `Room #${roomId}`;
};

const roomCode = (roomId: number) => roomMap.value.get(roomId)?.code ?? `Room ${roomId}`;

const selectedRoomLabel = computed(() => {
    if (selectedRoomIds.value.length === 0) return 'All rooms';
    if (selectedRoomIds.value.length === 1) return roomCode(selectedRoomIds.value[0]);
    return `${selectedRoomIds.value.length} rooms selected`;
});

function toggleRoomSelection(roomId: number) {
    if (selectedRoomIds.value.includes(roomId)) {
        selectedRoomIds.value = selectedRoomIds.value.filter((id) => id !== roomId);
        return;
    }

    selectedRoomIds.value = [...selectedRoomIds.value, roomId];
}

function clearRoomSelection() {
    selectedRoomIds.value = [];
}

function isRoomSelected(roomId: number) {
    return selectedRoomIds.value.includes(roomId);
}

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

const EVENT_CLASS: Record<DailySlotType, string> = {
    regular: 'border-sky-300 bg-sky-50 text-sky-900',
    cancellation: 'border-slate-300 bg-slate-100 text-slate-600 opacity-80',
    room_change: 'border-orange-300 bg-orange-50 text-orange-900',
    special_class: 'border-emerald-300 bg-emerald-50 text-emerald-900',
    makeup_class: 'border-violet-300 bg-violet-50 text-violet-900',
    maintenance: 'border-status-maintenance bg-status-maintenance-bg text-pup-gray-800',
    unavailable: 'border-pup-gray-600 bg-pup-gray-200 text-pup-gray-800',
    reserved: 'border-status-reserved-border bg-status-reserved-bg text-status-reserved',
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

function overlappingOverrides(slot: DailySlot): DailySlot[] {
    if (isOverrideSlot(slot)) return [];

    return filteredSlots.value.filter((candidate) => {
        return isOverrideSlot(candidate)
            && candidate.room_id === slot.room_id
            && slotsOverlap(slot, candidate);
    });
}

function blockingOverride(slot: DailySlot): DailySlot | null {
    const overrides = overlappingOverrides(slot);

    if (overrides.length === 0) return null;

    // Layer 1 override priority is already above schedules. This order only controls the label
    // if multiple room overrides accidentally overlap the same class in the same room.
    const priority: Record<string, number> = {
        maintenance: 1,
        unavailable: 2,
        reserved: 3,
    };

    return [...overrides].sort((a, b) => (priority[a.event_type] ?? 99) - (priority[b.event_type] ?? 99))[0];
}

function blockingOverrideLabel(slot: DailySlot): string {
    const override = blockingOverride(slot);

    return override ? `Blocked by ${EVENT_TYPE_LABEL[override.event_type]}` : '';
}

function blockingOverrideDetails(slot: DailySlot): string {
    const override = blockingOverride(slot);

    return override ? `${EVENT_TYPE_LABEL[override.event_type]} · ${formatTimeRange(override)}` : '';
}

function overrideHasAffectedClass(slot: DailySlot): boolean {
    if (!isOverrideSlot(slot)) return false;

    return filteredSlots.value.some((candidate) => {
        return isClassSlot(candidate)
            && candidate.room_id === slot.room_id
            && !['cancelled', 'auto_cancelled'].includes(candidate.status)
            && slotsOverlap(slot, candidate);
    });
}

function slotBlockClass(slot: DailySlot): string {
    if (isOverrideSlot(slot)) {
        const overlapState = overrideHasAffectedClass(slot)
            // Keep the old overlap behavior: the override stays as a diagonal room-state
            // band behind the class. Hover still brings whichever block is focused to front.
            ? 'z-20 border-dashed opacity-75 shadow-none'
            : 'z-30';

        return `${EVENT_CLASS[slot.event_type]} ${overlapState}`;
    }

    const base = YEAR_LEVEL_CLASS[yearLevel(slot)];
    const blocker = blockingOverride(slot);
    const exceptionState = isExceptionSlot(slot)
        // One unified exception design regardless of special/makeup/room-change/cancellation.
        ? 'border-2 border-pup-maroon/70 border-l-4 bg-white text-pup-maroon-deep shadow-sm'
        : '';

    if (['cancelled', 'auto_cancelled'].includes(slot.status)) {
        return `${isExceptionSlot(slot) ? exceptionState : base} z-30 opacity-60 grayscale`;
    }

    if (blocker) {
        // Do not add a heavy warning border. The class remains readable while the
        // diagonal override band communicates that the room status wins.
        return `${isExceptionSlot(slot) ? exceptionState : base} z-40 shadow-sm`;
    }

    if (slot.status === 'ongoing') {
        return `${isExceptionSlot(slot) ? exceptionState : base} z-30 ring-2 ring-green-400/60`;
    }

    return `${isExceptionSlot(slot) ? exceptionState : base} z-30`;
}

function slotBlockStyle(slot: DailySlot): Record<string, string> {
    if (isOverrideSlot(slot) && overrideHasAffectedClass(slot)) {
        return {
            backgroundImage:
                'repeating-linear-gradient(135deg, rgba(74, 11, 24, 0.14) 0px, rgba(74, 11, 24, 0.14) 3px, transparent 3px, transparent 9px)',
        };
    }

    return {};
}

const parseMinutes = (time: string) => {
    const [hour, minute] = time.split(':').map(Number);
    return hour * 60 + minute;
};

const visibleStartMinutes = START_HOUR * 60;
const visibleEndMinutes = END_HOUR * 60;

const clampMinutes = (minutes: number) => Math.min(Math.max(minutes, visibleStartMinutes), visibleEndMinutes);

const slotTop = (time: string) => ((clampMinutes(parseMinutes(time)) - visibleStartMinutes) / 60) * HOUR_HEIGHT;
const slotHeight = (start: string, end: string) => {
    const startMinutes = clampMinutes(parseMinutes(start));
    const endMinutes = clampMinutes(parseMinutes(end));

    return Math.max(((endMinutes - startMinutes) / 60) * HOUR_HEIGHT, 34);
};

const formatHour = (hour: number) => `${hour % 12 || 12}${hour < 12 ? 'AM' : 'PM'}`;
const formatTime = (time: string) => {
    const [hour, minute] = time.split(':').map(Number);
    return `${hour % 12 || 12}:${minute.toString().padStart(2, '0')}${hour < 12 ? 'AM' : 'PM'}`;
};
const formatTimeRange = (slot: DailySlot) => `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}`;

const selectedSlot = ref<DailySlot | null>(null);
const activeAction = ref<'cancel' | 'change-room' | 'start' | 'complete' | null>(null);
const showClassModal = ref(false);
const classModalType = ref<'special_class' | 'makeup_class'>('special_class');
const changeRoomId = ref<number | null>(null);
const actionReason = ref('');

const classForm = reactive({
    room_id: null as number | null,
    subject_code: '',
    subject_title: '',
    section: '',
    instructor_name: '',
    start_time: '08:00',
    end_time: '10:00',
    reason: '',
});

function resetClassForm() {
    classForm.room_id = rooms.value[0]?.id ?? null;
    classForm.subject_code = '';
    classForm.subject_title = '';
    classForm.section = '';
    classForm.instructor_name = '';
    classForm.start_time = '08:00';
    classForm.end_time = '10:00';
    classForm.reason = '';
}

function openClassModal() {
    classModalType.value = 'special_class';
    resetClassForm();
    showClassModal.value = true;
}

function closeClassModal() {
    showClassModal.value = false;
    resetClassForm();
}

function saveClassPreview() {
    if (!classForm.room_id || !classForm.subject_code || !classForm.subject_title || !classForm.section) return;

    localSlots.value.push({
        id: Date.now(),
        schedule_id: null,
        exception_id: Date.now(),
        room_id: classForm.room_id,
        event_date: selectedDate.value,
        source: 'exception',
        event_type: classModalType.value,
        status: 'pending',
        subject_code: classForm.subject_code,
        subject_title: classForm.subject_title,
        section: classForm.section,
        instructor_name: classForm.instructor_name || null,
        start_time: classForm.start_time,
        end_time: classForm.end_time,
        reason: classForm.reason || null,
    });

    closeClassModal();
}

function openSlot(slot: DailySlot) {
    selectedSlot.value = { ...slot };
    activeAction.value = null;
    actionReason.value = '';
    changeRoomId.value = null;
}

function closeSlot() {
    selectedSlot.value = null;
    activeAction.value = null;
    actionReason.value = '';
    changeRoomId.value = null;
}

function openAction(action: 'cancel' | 'change-room' | 'start' | 'complete') {
    activeAction.value = action;
    actionReason.value = '';
    changeRoomId.value = selectedSlot.value?.room_id ?? null;
}

function applyActionPreview() {
    if (!selectedSlot.value || !activeAction.value) return;

    const index = localSlots.value.findIndex((slot) => slot.id === selectedSlot.value?.id);
    if (index === -1) return;

    const current = { ...localSlots.value[index] };

    if (activeAction.value === 'cancel') {
        current.source = 'exception';
        current.event_type = 'cancellation';
        current.status = 'cancelled';
        current.reason = actionReason.value || current.reason || 'Cancelled by admin.';
    }

    if (activeAction.value === 'change-room' && changeRoomId.value && changeRoomId.value !== current.room_id) {
        current.source = 'exception';
        current.event_type = 'room_change';
        current.status = 'pending';
        current.original_room_id = current.original_room_id ?? current.room_id;
        current.original_room_code = current.original_room_code ?? roomCode(current.room_id);
        current.room_id = changeRoomId.value;
        current.reason = actionReason.value || current.reason || 'Room changed by admin.';
    }

    if (activeAction.value === 'start') {
        current.status = 'ongoing';
    }

    if (activeAction.value === 'complete') {
        current.status = 'completed';
    }

    localSlots.value.splice(index, 1, current);
    selectedSlot.value = { ...current };
    activeAction.value = null;
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
        <div class="flex flex-col gap-5" @click="isRoomFilterOpen = false">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <PillTabs :tabs="manageTabs" />

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="openClassModal"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-pup-maroon/15 bg-white px-3.5 py-2 text-sm font-medium text-pup-maroon shadow-sm transition hover:bg-pup-maroon-pale"
                    >
                        <Plus class="h-4 w-4" />
                        Request Class
                    </button>
                </div>
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
                    <p class="mt-2 text-2xl font-bold text-green-800">{{ freeRoomsNowCount }}</p>
                    <p class="text-xs text-green-600">selected rooms without active class</p>
                </div>
                <div class="rounded-xl border border-red-100 bg-red-50/70 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-red-500">Occupied Now</p>
                    <p class="mt-2 text-2xl font-bold text-red-800">{{ occupiedNowCount }}</p>
                    <p class="text-xs text-red-600">classes currently using rooms</p>
                </div>
                <div class="rounded-xl border border-amber-100 bg-amber-50/70 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Upcoming</p>
                    <p class="mt-2 text-2xl font-bold text-amber-800">{{ upcomingSoonCount }}</p>
                    <p class="text-xs text-amber-600">classes within the next 30 minutes</p>
                </div>
                <div class="rounded-xl border border-orange-100 bg-orange-50/70 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-orange-600">Exceptions / Cancelled</p>
                    <p class="mt-2 text-2xl font-bold text-orange-800">{{ exceptionCount }} / {{ cancelledCount }}</p>
                    <p class="text-xs text-orange-600">changes from baseline / freed slots</p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="flex flex-col gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Date
                            <input
                                v-model="selectedDate"
                                type="date"
                                @change="goToSelectedDate"
                                class="h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-700 shadow-sm transition focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15"
                            />
                        </label>

                        <div class="relative flex flex-col gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Room
                            <button
                                type="button"
                                @click.stop="isRoomFilterOpen = !isRoomFilterOpen"
                                class="flex h-11 items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-3 text-left text-sm font-medium normal-case tracking-normal text-gray-700 shadow-sm transition hover:border-pup-maroon/40 focus:border-pup-maroon focus:outline-none focus:ring-2 focus:ring-pup-maroon/15"
                            >
                                <span>{{ selectedRoomLabel }}</span>
                                <ChevronDown class="h-4 w-4 text-gray-400" />
                            </button>

                            <div
                                v-if="isRoomFilterOpen"
                                class="absolute left-0 top-full z-30 mt-2 w-full min-w-[260px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl"
                                @click.stop
                            >
                                <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Select rooms</span>
                                    <button
                                        type="button"
                                        @click="clearRoomSelection"
                                        class="text-xs font-semibold normal-case tracking-normal text-pup-maroon hover:underline"
                                    >
                                        All rooms
                                    </button>
                                </div>
                                <div class="max-h-64 overflow-y-auto p-1.5">
                                    <button
                                        v-for="room in rooms"
                                        :key="room.id"
                                        type="button"
                                        @click="toggleRoomSelection(room.id)"
                                        class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-sm normal-case tracking-normal transition hover:bg-pup-maroon-pale/70"
                                        :class="isRoomSelected(room.id) ? 'bg-pup-maroon-pale text-pup-maroon' : 'text-gray-700'"
                                    >
                                        <span class="font-mono font-semibold">{{ room.code }}</span>
                                        <span
                                            class="flex h-4 w-4 items-center justify-center rounded border"
                                            :class="isRoomSelected(room.id) ? 'border-pup-maroon bg-pup-maroon text-white' : 'border-gray-300 bg-white'"
                                        >
                                            <CheckCircle2 v-if="isRoomSelected(room.id)" class="h-3 w-3" />
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <label class="flex flex-col gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Type
                            <select
                                v-model="selectedType"
                                class="h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium normal-case tracking-normal text-gray-700 shadow-sm transition focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15"
                            >
                                <option value="all">All types</option>
                                <option value="regular">Regular</option>
                                <option value="cancellation">Cancellation</option>
                                <option value="room_change">Room Change</option>
                                <option value="special_class">Special Class</option>
                                <option value="makeup_class">Makeup Class</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="unavailable">Unavailable</option>
                                <option value="reserved">Reserved</option>
                            </select>
                        </label>
                    </div>

                    <div class="inline-flex rounded-xl border border-gray-200 bg-gray-50 p-1">
                        <button
                            @click="viewMode = 'room'"
                            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="viewMode === 'room' ? 'bg-pup-maroon text-white shadow-sm' : 'text-gray-600 hover:bg-white'"
                        >
                            <LayoutGrid class="h-4 w-4" />
                            Room View
                        </button>
                        <button
                            @click="viewMode = 'table'"
                            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="viewMode === 'table' ? 'bg-pup-maroon text-white shadow-sm' : 'text-gray-600 hover:bg-white'"
                        >
                            <Table2 class="h-4 w-4" />
                            Table View
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="viewMode === 'room'" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="min-w-[1120px]">
                    <div class="flex border-b border-pup-maroon-deep bg-pup-maroon text-white">
                        <div class="flex w-16 shrink-0 items-center justify-center border-r border-white/15 bg-pup-maroon-deep px-2 py-3 text-[10px] font-bold uppercase tracking-wider text-pup-gold-light">
                            Time
                        </div>
                        <div
                            v-for="room in roomsForGrid"
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
                            v-for="room in roomsForGrid"
                            :key="room.id"
                            class="relative min-w-[118px] flex-1 border-r border-gray-100 last:border-r-0"
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
                                v-for="slot in room.slots"
                                :key="slot.id"
                                type="button"
                                class="group absolute inset-x-1 overflow-hidden rounded-md border px-1.5 py-1 text-left shadow-sm transition hover:z-50 hover:brightness-95 hover:shadow-md"
                                :class="slotBlockClass(slot)"
                                :style="{
                                    top: slotTop(slot.start_time) + 3 + 'px',
                                    height: slotHeight(slot.start_time, slot.end_time) - 6 + 'px',
                                    ...slotBlockStyle(slot),
                                }"
                                @click="openSlot(slot)"
                            >
                                <div class="flex items-start justify-between gap-1">
                                    <p class="line-clamp-2 text-[11px] font-bold leading-snug">
                                        {{ slot.source === 'override' ? EVENT_TYPE_LABEL[slot.event_type] : slot.subject_code }}
                                    </p>
                                    <div class="flex shrink-0 flex-col items-end gap-1">
                                        <span
                                            v-if="blockingOverrideLabel(slot)"
                                            class="rounded bg-pup-maroon/90 px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-white"
                                        >
                                            Blocked
                                        </span>
                                        <span
                                            v-if="isExceptionSlot(slot)"
                                            class="rounded border border-pup-maroon/25 bg-pup-maroon-pale px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-pup-maroon"
                                        >
                                            Exception
                                        </span>
                                    </div>
                                </div>

                                <template v-if="slot.source === 'override'">
                                    <p class="line-clamp-2 text-[10px] font-semibold leading-snug opacity-80">
                                        Room status override
                                    </p>
                                    <p class="mt-0.5 text-[10px] opacity-65">{{ formatTimeRange(slot) }}</p>
                                </template>

                                <template v-else>
                                    <p class="line-clamp-2 text-[10px] font-semibold leading-snug opacity-80">
                                        {{ slot.subject_title }}
                                    </p>
                                    <p class="mt-0.5 text-[10px] opacity-65">{{ formatTimeRange(slot) }}</p>
                                    <p class="truncate text-[10px] opacity-55">{{ slot.section }}</p>
                                    <p
                                        v-if="blockingOverrideLabel(slot)"
                                        class="mt-0.5 truncate text-[9px] font-semibold text-pup-maroon"
                                    >
                                        {{ blockingOverrideLabel(slot) }}
                                    </p>
                                    <p v-if="slot.event_type === 'room_change'" class="mt-0.5 truncate text-[9px] font-semibold opacity-70">
                                        From {{ slot.original_room_code }}
                                    </p>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
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
                            <template v-for="group in tableTimeGroups" :key="group.key">
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
                                        <div v-if="blockingOverrideDetails(slot)" class="mt-1 inline-flex rounded-full bg-pup-maroon-pale px-2 py-0.5 text-[11px] font-bold text-pup-maroon">
                                            Room status: {{ blockingOverrideDetails(slot) }}
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
                                            @click="openSlot(slot)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-pup-maroon hover:text-pup-maroon"
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                            View
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="tableTimeGroups.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                                    No schedule items match the selected filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>

    <div v-if="selectedSlot" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4">
        <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-pup-maroon">Schedule item</p>
                    <h3 class="mt-1 text-xl font-bold text-gray-900">{{ selectedSlot.subject_code }} · {{ selectedSlot.subject_title }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ selectedSlot.section }} · {{ formatTimeRange(selectedSlot) }}</p>
                </div>
                <button @click="closeSlot" class="rounded-full p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="space-y-5 px-6 py-5">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <p class="text-xs font-semibold uppercase text-gray-400">Room</p>
                        <p class="mt-1 font-semibold text-gray-900">{{ roomLabel(selectedSlot.room_id) }}</p>
                        <p v-if="selectedSlot.original_room_code" class="mt-1 text-xs font-semibold text-orange-600">
                            Moved from {{ selectedSlot.original_room_code }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <p class="text-xs font-semibold uppercase text-gray-400">Instructor</p>
                        <p class="mt-1 font-semibold text-gray-900">{{ selectedSlot.instructor_name || '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <p class="text-xs font-semibold uppercase text-gray-400">Type</p>
                        <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="EVENT_BADGE[selectedSlot.event_type]">
                            {{ EVENT_TYPE_LABEL[selectedSlot.event_type] }}
                        </span>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <p class="text-xs font-semibold uppercase text-gray-400">Status</p>
                        <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="STATUS_BADGE[selectedSlot.status]">
                            {{ STATUS_LABEL[selectedSlot.status] }}
                        </span>
                    </div>
                </div>

                <div v-if="selectedSlot.reason" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <span class="font-semibold">Reason:</span> {{ selectedSlot.reason }}
                </div>

                <div v-if="blockingOverrideLabel(selectedSlot)" class="rounded-xl border border-pup-maroon/20 bg-pup-maroon-pale px-4 py-3 text-sm text-pup-maroon">
                    <span class="font-semibold">Room override priority:</span>
                    {{ blockingOverrideDetails(selectedSlot) }}. The override is the active room status and wins over this class for the overlapping time.
                </div>

                <div v-if="selectedSlot.source === 'override' && overrideHasAffectedClass(selectedSlot)" class="rounded-xl border border-pup-maroon/20 bg-pup-maroon-pale px-4 py-3 text-sm text-pup-maroon">
                    <span class="font-semibold">Override priority:</span>
                    This room override overlaps at least one class. The room status control wins over schedule items during the overlap.
                </div>

                <div v-if="!activeAction" class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-4">
                    <button
                        v-if="selectedSlot.source !== 'override' && selectedSlot.status !== 'cancelled'"
                        @click="openAction('cancel')"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        <Ban class="h-4 w-4" />
                        Cancel
                    </button>
                    <button
                        v-if="selectedSlot.source !== 'override' && selectedSlot.status !== 'cancelled'"
                        @click="openAction('change-room')"
                        class="inline-flex items-center gap-2 rounded-lg border border-orange-200 px-3 py-2 text-sm font-semibold text-orange-700 transition hover:bg-orange-50"
                    >
                        <ArrowRightLeft class="h-4 w-4" />
                        Change Room
                    </button>
                    <button
                        v-if="selectedSlot.source !== 'override' && ['scheduled', 'pending'].includes(selectedSlot.status)"
                        @click="openAction('start')"
                        class="inline-flex items-center gap-2 rounded-lg border border-green-200 px-3 py-2 text-sm font-semibold text-green-700 transition hover:bg-green-50"
                    >
                        <Play class="h-4 w-4" />
                        Mark Started
                    </button>
                    <button
                        v-if="selectedSlot.source !== 'override' && selectedSlot.status === 'ongoing'"
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
                        This is preview behavior for the frontend. Backend wiring will later create or update schedule exceptions.
                    </p>

                    <label v-if="activeAction === 'change-room'" class="mt-4 flex flex-col gap-1 text-sm font-medium text-gray-600">
                        New room
                        <select v-model="changeRoomId" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon">
                            <option v-for="room in rooms" :key="room.id" :value="room.id">
                                {{ room.code }} · {{ room.name }}
                            </option>
                        </select>
                    </label>

                    <label v-if="['cancel', 'change-room'].includes(activeAction)" class="mt-4 flex flex-col gap-1 text-sm font-medium text-gray-600">
                        Reason
                        <textarea
                            v-model="actionReason"
                            rows="3"
                            class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon"
                            placeholder="Optional note for this daily operation"
                        />
                    </label>

                    <div class="mt-4 flex justify-end gap-2">
                        <button @click="activeAction = null" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-white">
                            Back
                        </button>
                        <button @click="applyActionPreview" class="rounded-lg bg-pup-maroon px-3 py-2 text-sm font-semibold text-white hover:bg-pup-maroon-deep">
                            Apply Preview
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="showClassModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4">
        <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-pup-maroon">Daily operation</p>
                    <h3 class="mt-1 text-xl font-bold text-gray-900">Request Class</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Create a one-date class entry for {{ selectedDateLabel }} and classify it as a special or makeup class.
                    </p>
                </div>
                <button @click="closeClassModal" class="rounded-full p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <p class="text-sm font-semibold text-gray-700">Request type</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            @click="classModalType = 'special_class'"
                            :class="[
                                'rounded-xl border px-4 py-3 text-left transition',
                                classModalType === 'special_class'
                                    ? 'border-pup-maroon bg-pup-maroon-pale text-pup-maroon shadow-sm'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-pup-maroon/30 hover:bg-pup-maroon-pale/40',
                            ]"
                        >
                            <span class="block text-sm font-bold">Special Class</span>
                            <span class="mt-1 block text-xs leading-relaxed opacity-80">
                                One-time class added for the selected date with no regular weekly schedule.
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="classModalType = 'makeup_class'"
                            :class="[
                                'rounded-xl border px-4 py-3 text-left transition',
                                classModalType === 'makeup_class'
                                    ? 'border-pup-maroon bg-pup-maroon-pale text-pup-maroon shadow-sm'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-pup-maroon/30 hover:bg-pup-maroon-pale/40',
                            ]"
                        >
                            <span class="block text-sm font-bold">Makeup Class</span>
                            <span class="mt-1 block text-xs leading-relaxed opacity-80">
                                Replacement class for a missed or adjusted session.
                            </span>
                        </button>
                    </div>
                </div>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    Room
                    <select v-model="classForm.room_id" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon">
                        <option v-for="room in rooms" :key="room.id" :value="room.id">
                            {{ room.code }} · {{ room.name }}
                        </option>
                    </select>
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    Section
                    <input v-model="classForm.section" type="text" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" placeholder="BSCPE 4-2" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    Subject code
                    <input v-model="classForm.subject_code" type="text" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" placeholder="CMPE 499" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    Subject title
                    <input v-model="classForm.subject_title" type="text" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" placeholder="Capstone Consultation" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    Start time
                    <input v-model="classForm.start_time" type="time" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    End time
                    <input v-model="classForm.end_time" type="time" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600 sm:col-span-2">
                    Instructor
                    <input v-model="classForm.instructor_name" type="text" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" placeholder="Instructor name" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600 sm:col-span-2">
                    Reason / note
                    <textarea v-model="classForm.reason" rows="3" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" :placeholder="classModalType === 'special_class' ? 'Optional reason for this special class' : 'Optional reason for this makeup class'" />
                </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                <button @click="closeClassModal" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-white">
                    Cancel
                </button>
                <button @click="saveClassPreview" class="rounded-lg bg-pup-maroon px-4 py-2 text-sm font-semibold text-white hover:bg-pup-maroon-deep">
                    {{ classModalType === 'special_class' ? 'Save Special Class' : 'Save Makeup Class' }}
                </button>
            </div>
        </div>
    </div>
</template>
