<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, CalendarDays, Clock3, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import DailyFilters from '@/pages/Admin/Operations/Components/DailyFilters.vue';
import DailyRoomGrid from '@/pages/Admin/Operations/Components/DailyRoomGrid.vue';
import ScheduleSlotModal from './Components/ScheduleSlotModal.vue';
import DailyTableView from '@/pages/Admin/Operations/Components/DailyTableView.vue';
import type { CurrentTerm, DailySlot, DailySlotType, Room, SharedProps, ViewMode } from '@/pages/Admin/Operations/Components/type';

interface Props {
    currentTerm?: CurrentTerm | null;
    rooms?: Room[];
    daily_schedules?: DailySlot[];
    selected_date?: string;
    operation_term_id?: number | null;
    claim_grace_minutes?: number;
}

const props = withDefaults(defineProps<Props>(), {
    rooms: () => [],
    daily_schedules: () => [],
    selected_date: '',
    claim_grace_minutes: 60,
});

const page = usePage<SharedProps>();
const currentTerm = computed(() => page.props.currentTerm ?? props.currentTerm ?? null);
const hasOperationTerm = computed(() => Boolean(props.operation_term_id ?? currentTerm.value?.id));

const scheduleEndpoint = '/kiosk/schedules/';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Schedule', href: scheduleEndpoint }];

const pageheader: PageHeader = {
    title: 'Schedule',
    desc: "View today's room schedules, class details, cancellations, room changes, and room status updates.",
};

const selectedDate = ref(props.selected_date || todayIso());
const viewMode = ref<ViewMode>('room');
const selectedRoomIds = ref<number[]>([]);
const selectedType = ref<'all' | DailySlotType>('all');
const claimGraceMinutes = computed(() => Math.max(1, Number(props.claim_grace_minutes || 60)));

const currentDateTime = ref(new Date());
const lastUpdatedAt = ref(new Date());
let clockTimer: number | undefined;
let reloadTimer: number | undefined;
const isRefreshing = ref(false);

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

function formatTime(time: string): string {
    const [hour, minute] = time.split(':').map(Number);
    return `${hour % 12 || 12}:${minute.toString().padStart(2, '0')}${hour < 12 ? 'AM' : 'PM'}`;
}

function formatTimeRange(slot: DailySlot): string {
    return `${formatTime(slot.start_time)}–${formatTime(slot.end_time)}`;
}

function slotStartDate(slot: DailySlot): Date | null {
    const date = new Date(`${slot.event_date}T${slot.start_time}:00`);

    return Number.isNaN(date.getTime()) ? null : date;
}

function slotClaimDeadline(slot: DailySlot): Date | null {
    const start = slotStartDate(slot);
    if (!start) return null;

    const deadline = new Date(start);
    deadline.setMinutes(deadline.getMinutes() + claimGraceMinutes.value);

    return deadline;
}

function localIsoString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hour = String(date.getHours()).padStart(2, '0');
    const minute = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hour}:${minute}:00`;
}

function timeString(date: Date): string {
    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function isClientSideUnclaimed(slot: DailySlot): boolean {
    if (slot.source !== 'schedule' || slot.event_type !== 'regular') return false;
    if (!['scheduled', 'pending'].includes(slot.status)) return false;
    if (slot.actual_start || slot.actual_end || slot.claimed_at) return false;

    const deadline = slotClaimDeadline(slot);
    if (!deadline) return false;

    const endDate = new Date(`${slot.event_date}T${slot.end_time}:00`);
    if (!Number.isNaN(endDate.getTime()) && deadline.getTime() >= endDate.getTime()) return false;

    return currentDateTime.value.getTime() >= deadline.getTime();
}

function applyClientSideUnclaimedStatus(slot: DailySlot): DailySlot {
    if (slot.source !== 'schedule' || slot.event_type !== 'regular') return slot;

    const deadline = slotClaimDeadline(slot);
    const enrichedSlot: DailySlot = {
        ...slot,
        claim_deadline_at: deadline ? localIsoString(deadline) : (slot.claim_deadline_at ?? null),
        claim_deadline_time: deadline ? timeString(deadline) : (slot.claim_deadline_time ?? null),
    };

    return isClientSideUnclaimed(slot) ? { ...enrichedSlot, status: 'unclaimed' } : enrichedSlot;
}

const displaySlots = computed<DailySlot[]>(() => localSlots.value.map(applyClientSideUnclaimedStatus));

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
    const diffInSeconds = Math.floor((currentDateTime.value.getTime() - lastUpdatedAt.value.getTime()) / 1000);

    if (diffInSeconds < 10) return 'Just now';
    if (diffInSeconds < 60) return `${diffInSeconds} sec ago`;

    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) return `${diffInMinutes} min ago`;

    const diffInHours = Math.floor(diffInMinutes / 60);
    return `${diffInHours} hr${diffInHours > 1 ? 's' : ''} ago`;
});

function refreshSchedule() {
    if (isRefreshing.value) return;

    isRefreshing.value = true;

    router.reload({
        only: ['rooms', 'daily_schedules', 'selected_date', 'operation_term_id', 'claim_grace_minutes'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            lastUpdatedAt.value = new Date();
        },
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
}

function goToSelectedDate() {
    router.get(
        scheduleEndpoint,
        { selected_date: selectedDate.value },
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
            onSuccess: () => {
                lastUpdatedAt.value = new Date();
            },
        },
    );
}

const filteredSlots = computed(() => {
    return displaySlots.value
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

const nowMinutes = computed(() => {
    if (selectedDate.value !== todayIso()) return null;

    const now = currentDateTime.value;
    return now.getHours() * 60 + now.getMinutes();
});

function isClassSlot(slot: DailySlot): boolean {
    return slot.source !== 'override';
}

function isSlotHappeningNow(slot: DailySlot): boolean {
    if (nowMinutes.value === null) return false;
    if (['cancelled', 'auto_cancelled', 'unclaimed', 'completed'].includes(slot.status)) return false;

    return parseMinutes(slot.start_time) <= nowMinutes.value && nowMinutes.value < parseMinutes(slot.end_time);
}

function isClassSlotAwaitingClaim(slot: DailySlot): boolean {
    return isClassSlot(slot) && ['scheduled', 'pending'].includes(slot.status);
}

const exceptionCount = computed(() => filteredSlots.value.filter((slot) => slot.source === 'exception').length);
const occupiedNowCount = computed(() => filteredSlots.value.filter((slot) => isSlotHappeningNow(slot) && isClassSlot(slot) && slot.status === 'ongoing').length);
const reservedNowCount = computed(() => filteredSlots.value.filter((slot) => isSlotHappeningNow(slot) && isClassSlotAwaitingClaim(slot)).length);
const freeRoomsNowCount = computed(() => {
    const busyRoomIds = new Set(filteredSlots.value.filter(isSlotHappeningNow).map((slot) => slot.room_id));

    return roomsForGrid.value.filter((room) => !busyRoomIds.has(room.id)).length;
});
const upcomingSoonCount = computed(() => {
    if (nowMinutes.value === null) return 0;

    return filteredSlots.value.filter((slot) => {
        if (!isClassSlot(slot) || ['cancelled', 'auto_cancelled', 'unclaimed', 'completed', 'ongoing'].includes(slot.status)) return false;

        const start = parseMinutes(slot.start_time);
        return start >= nowMinutes.value && start <= nowMinutes.value + 30;
    }).length;
});

const summaryStats = computed(() => ({
    freeRoomsNowCount: freeRoomsNowCount.value,
    reservedNowCount: reservedNowCount.value,
    occupiedNowCount: occupiedNowCount.value,
    upcomingSoonCount: upcomingSoonCount.value,
    exceptionCount: exceptionCount.value,
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

function openSlot(slot: DailySlot) {
    selectedSlot.value = { ...slot };
}

function closeSlot() {
    selectedSlot.value = null;
}

onMounted(() => {
    clockTimer = window.setInterval(() => {
        currentDateTime.value = new Date();
    }, 1000);

    reloadTimer = window.setInterval(() => {
        refreshSchedule();
    }, 15000);
});

onUnmounted(() => {
    if (clockTimer) {
        window.clearInterval(clockTimer);
    }

    if (reloadTimer) {
        window.clearInterval(reloadTimer);
    }
});
</script>

<template>
    <Head title="Schedule" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <div class="flex flex-col gap-5">
            <div
                v-if="!hasOperationTerm"
                class="flex items-start gap-3 rounded-xl border border-pup-gold/40 bg-pup-gold-pale/70 px-4 py-3 text-sm text-pup-maroon-dark"
            >
                <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-pup-gold-dark" />
                <div>
                    <p class="font-semibold">No active academic term is set.</p>
                    <p class="text-pup-maroon-dark/80">The public schedule can still open, but daily schedule data should come from the active term.</p>
                </div>
            </div>

            <div
                class="overflow-hidden rounded-2xl border border-pup-gold/40 bg-gradient-to-r from-pup-maroon via-pup-maroon to-pup-maroon-deep text-white shadow-lg shadow-pup-maroon/10"
            >
                <div class="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/12 ring-1 ring-white/15">
                            <CalendarDays class="h-5 w-5 text-pup-gold-light" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-pup-gold-light">Public schedule board</p>
                            <p class="mt-1 text-lg font-semibold leading-tight">
                                Schedule:
                                <span class="text-pup-gold-light">{{ selectedDateLabel }}</span>
                            </p>
                            <p class="mt-1 text-sm text-white/70">View today's classes, room changes, cancellations, and room status updates.</p>
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
                            :disabled="isRefreshing"
                            @click="refreshSchedule"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-xs font-bold text-pup-maroon shadow-sm transition hover:bg-pup-gold-pale disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            <RefreshCw class="h-3.5 w-3.5" :class="isRefreshing ? 'animate-spin' : ''" />
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

                <div class="rounded-xl border border-pup-gold/30 bg-pup-gold-pale/50 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-pup-maroon">Reserved Now</p>
                    <p class="mt-2 text-2xl font-bold text-pup-maroon-deep">
                        {{ summaryStats.reservedNowCount }}
                        <span v-if="summaryStats.upcomingSoonCount" class="text-base font-semibold">({{ summaryStats.upcomingSoonCount }} soon)</span>
                    </p>
                    <p class="text-xs text-pup-maroon/70">classes waiting to start</p>
                </div>

                <div class="rounded-xl border border-red-100 bg-red-50/70 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-red-500">Occupied Now</p>
                    <p class="mt-2 text-2xl font-bold text-red-800">{{ summaryStats.occupiedNowCount }}</p>
                    <p class="text-xs text-red-600">rooms currently used for classes</p>
                </div>

                <div class="rounded-xl border border-orange-100 bg-orange-50/70 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-orange-600">Daily Changes</p>
                    <p class="mt-2 text-2xl font-bold text-orange-800">{{ summaryStats.exceptionCount }}</p>
                    <p class="text-xs text-orange-600">cancellations, changes, and special classes</p>
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
                :selected-date="selectedDate"
                :current-date-time="currentDateTime"
                @open-slot="openSlot"
            />

            <DailyTableView
                v-else
                :groups="tableTimeGroups"
                :rooms="rooms"
                :all-slots="filteredSlots"
                :selected-date="selectedDate"
                :current-date-time="currentDateTime"
                @open-slot="openSlot"
            />
        </div>
    </AppLayout>

    <ScheduleSlotModal
        :slot="selectedSlot"
        :rooms="rooms"
        :all-slots="filteredSlots"
        @close="closeSlot"
    />
</template>
