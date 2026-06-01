<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import RoomStatusClearModal from './Components/RoomStatusClearModal.vue';
import RoomStatusViewModal from './Components/RoomStatusViewModal.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Archive, Building2, CalendarClock, CalendarDays, CheckCircle2, ChevronDown, ChevronLeft, ChevronRight, Clock, Eye, Plus, Power, Search, ShieldAlert, Wrench, School } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// --- Page Types ---
interface RoomOption {
    id: number;
    code: string;
    name: string;
    room_type?: string;
}

type OverrideStatus = 'maintenance' | 'unavailable' | 'reserved';
type OverrideState = 'active' | 'upcoming' | 'history';
type OverrideFilter = 'all' | OverrideState;

interface RoomOverrideItem {
    id: number;
    room_id: number;
    room_code: string;
    room_name: string;
    status: OverrideStatus;
    reason: string | null;
    starts_at: string;
    ends_at: string | null;
    is_active: boolean;
    created_by_name: string;
    updated_by_name?: string | null;
}

type RoomStatusForm = {
    room_id: number;
    status: OverrideStatus;
    reason: string;
    starts_at: string;
    ends_at: string;
    indefinite: boolean;
};

type ModalMode = 'create' | 'view' | 'clear';


// --- Page Props and Template Setup ---
const props = defineProps<{
    rooms: RoomOption[];
    overrides: RoomOverrideItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Operations', href: '/admin/operations/daily' },
    { title: 'Room Status', href: '/admin/operations/room-status' },
];

const pageheader: PageHeader = {
    title: 'Room Status',
    desc: 'Manage room restrictions, maintenance blocks, and non-class reservations.',
};

const operationTabs = [
    { label: 'Daily Schedule', href: '/admin/operations/daily', icon: CalendarDays },
    { label: 'Room Status', href: '/admin/operations/room-status', icon: School },
];

const overrideTabs: Array<{ label: string; value: OverrideFilter }> = [
    { label: 'All', value: 'all' },
    { label: 'Active', value: 'active' },
    { label: 'Upcoming', value: 'upcoming' },
    { label: 'Archive', value: 'history' },
];

const overrideStatuses: OverrideStatus[] = ['maintenance', 'unavailable', 'reserved'];


// ---- Helpers -----
function toLocalInputValue(dateOrString: Date | string): string {
    const date = typeof dateOrString === 'string' ? new Date(dateOrString) : dateOrString;
    const timezoneOffset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16);
}

function parseDate(value: string): Date {
    return new Date(value);
}

const activeTab = ref<OverrideFilter>('all');
const search = ref('');
const selectedStatus = ref<'all' | OverrideStatus>('all');
const selectedRoom = ref('all');
const activeModal = ref<ModalMode | null>(null);
const selectedOverride = ref<RoomOverrideItem | null>(null);
const isDetailsEditing = ref(false);
const isClearing = ref(false);

const form = useForm<RoomStatusForm>({
    room_id: props.rooms[0]?.id ?? 1,
    status: 'maintenance',
    reason: '',
    starts_at: toLocalInputValue(new Date()),
    ends_at: '',
    indefinite: false,
});

function overrideState(item: RoomOverrideItem): OverrideState {
    const current = new Date();
    const startsAt = parseDate(item.starts_at);
    const endsAt = item.ends_at ? parseDate(item.ends_at) : null;

    if (!item.is_active || (endsAt && endsAt <= current)) {
        return 'history';
    }

    if (startsAt > current) {
        return 'upcoming';
    }

    return 'active';
}

const filteredOverrides = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return [...props.overrides]
        .filter((item) => {
            const state = overrideState(item);
            const matchesTab = activeTab.value === 'all' ? state !== 'history' : state === activeTab.value;
            const matchesStatus = selectedStatus.value === 'all' || item.status === selectedStatus.value;
            const matchesRoom = selectedRoom.value === 'all' || item.room_id === Number(selectedRoom.value);
            const matchesSearch =
                !needle ||
                [item.room_code, item.room_name, item.status, item.reason ?? '', item.created_by_name, item.updated_by_name ?? ''].some((value) =>
                    value.toLowerCase().includes(needle),
                );

            return matchesTab && matchesStatus && matchesRoom && matchesSearch;
        })
        .sort((a, b) => parseDate(a.starts_at).getTime() - parseDate(b.starts_at).getTime());
});

// --- Client-Side Pagination Logic ---
const currentPage = ref(1);
const itemsPerPage = 10;

const totalPages = computed(() => Math.ceil(filteredOverrides.value.length / itemsPerPage));

const paginatedOverrides = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredOverrides.value.slice(start, end);
});

// Reset to page 1 if the underlying filtered list changes
watch(() => filteredOverrides.value.length, () => {
    if (currentPage.value > totalPages.value) {
        currentPage.value = Math.max(1, totalPages.value);
    }
});

function prevPage() {
    if (currentPage.value > 1) currentPage.value--;
}

function nextPage() {
    if (currentPage.value < totalPages.value) currentPage.value++;
}
// ------------------------------------

const summary = computed(() => {
    const active = props.overrides.filter((item) => overrideState(item) === 'active');
    const upcoming = props.overrides.filter((item) => overrideState(item) === 'upcoming');

    return {
        active: active.length,
        upcoming: upcoming.length,
        maintenance: active.filter((item) => item.status === 'maintenance').length,
        unavailable: active.filter((item) => item.status === 'unavailable').length,
        reserved: active.filter((item) => item.status === 'reserved').length,
    };
});

function formatDateTime(value: string | null): string {
    if (!value) return 'Indefinite';

    return new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(parseDate(value));
}

function durationLabel(item: RoomOverrideItem): string {
    if (!item.ends_at) return 'Until manually cleared';

    const diff = parseDate(item.ends_at).getTime() - parseDate(item.starts_at).getTime();
    const hours = Math.max(Math.round(diff / (60 * 60 * 1000)), 1);

    return hours === 1 ? '1 hour' : `${hours} hours`;
}

function statusLabel(status: OverrideStatus): string {
    return {
        maintenance: 'Maintenance',
        unavailable: 'Unavailable',
        reserved: 'Reserved',
    }[status];
}

function statusBadgeClass(status: OverrideStatus): string {
    return {
        maintenance: 'border-gray-200 bg-status-maintenance-bg text-gray-600',
        unavailable: 'border-gray-300 bg-gray-200 text-gray-800',
        reserved: 'border-status-reserved-border bg-status-reserved-bg text-status-reserved',
    }[status];
}

function stateBadgeClass(state: OverrideState): string {
    return {
        active: 'border-green-200 bg-green-100 text-green-700',
        upcoming: 'border-amber-200 bg-amber-50 text-amber-700',
        history: 'border-gray-200 bg-gray-100 text-gray-500',
    }[state];
}

function stateLabel(state: OverrideState): string {
    return {
        active: 'Active',
        upcoming: 'Upcoming',
        history: 'Archive',
    }[state];
}

function filterLabel(filter: OverrideFilter): string {
    return filter === 'all' ? 'All' : stateLabel(filter);
}

function filterBadgeClass(filter: OverrideFilter): string {
    if (filter === 'all') return 'border-pup-maroon/20 bg-pup-maroon-pale text-pup-maroon';

    return stateBadgeClass(filter);
}

function resetForm(item?: RoomOverrideItem | null) {
    form.room_id = item?.room_id ?? props.rooms[0]?.id ?? 1;
    form.status = item?.status ?? 'maintenance';
    form.reason = item?.reason ?? '';
    form.starts_at = item?.starts_at ? toLocalInputValue(item.starts_at) : toLocalInputValue(new Date());
    form.ends_at = item?.ends_at ? toLocalInputValue(item.ends_at) : '';
    form.indefinite = item ? !item.ends_at : false;
    form.clearErrors();
}

function applyPayloadToForm(payload: RoomStatusForm) {
    form.room_id = Number(payload.room_id);
    form.status = payload.status;
    form.reason = payload.reason ?? '';
    form.starts_at = payload.starts_at;
    form.indefinite = Boolean(payload.indefinite);
    form.ends_at = form.indefinite ? '' : payload.ends_at || '';
}

function openCreate() {
    selectedOverride.value = null;
    isDetailsEditing.value = false;
    resetForm(null);
    activeModal.value = 'create';
}

function openView(item: RoomOverrideItem) {
    selectedOverride.value = item;
    isDetailsEditing.value = false;
    resetForm(item);
    activeModal.value = 'view';
}

function enableDetailsEdit() {
    if (!selectedOverride.value) return;

    resetForm(selectedOverride.value);
    isDetailsEditing.value = true;
}

function openClear(item: RoomOverrideItem) {
    selectedOverride.value = item;
    isDetailsEditing.value = false;
    activeModal.value = 'clear';
}

function closeModal() {
    activeModal.value = null;
    selectedOverride.value = null;
    isDetailsEditing.value = false;
    resetForm(null);
}

function cancelOrCloseModal() {
    if (activeModal.value === 'view' && isDetailsEditing.value && selectedOverride.value) {
        isDetailsEditing.value = false;
        resetForm(selectedOverride.value);
        return;
    }

    closeModal();
}

function saveOverride(payload: RoomStatusForm) {
    applyPayloadToForm(payload);
    form.clearErrors();

    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    };

    if (activeModal.value === 'create') {
        form.post('/admin/operations/room-status', options);
        return;
    }

    if (activeModal.value === 'view' && isDetailsEditing.value && selectedOverride.value) {
        form.patch(`/admin/operations/room-status/${selectedOverride.value.id}`, options);
    }
}

function clearOverride() {
    if (!selectedOverride.value) return;

    isClearing.value = true;

    router.patch(
        `/admin/operations/room-status/${selectedOverride.value.id}/clear`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onFinish: () => {
                isClearing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Room Status" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <PillTabs :tabs="operationTabs" />

                <button
                    type="button"
                    @click="openCreate"
                    class="flex items-center gap-1.5 rounded-lg bg-pup-maroon px-3.5 py-2 text-sm font-medium text-white transition hover:bg-pup-maroon-deep"
                >
                    <Plus class="h-4 w-4" />
                    Create Override
                </button>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <div class="flex gap-2">
                    <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        Room Status is only for physical/admin room states: maintenance, unavailable, or reserved. Class cancellations and room changes belong in Daily Schedule.
                    </p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Active</p>
                        <CheckCircle2 class="h-4 w-4 text-status-available" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">{{ summary.active }}</p>
                    <p class="mt-1 text-xs text-gray-500">Currently affecting room status</p>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Upcoming</p>
                        <CalendarClock class="h-4 w-4 text-status-warning" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">{{ summary.upcoming }}</p>
                    <p class="mt-1 text-xs text-gray-500">Scheduled to start later</p>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Maintenance</p>
                        <Wrench class="h-4 w-4 text-gray-500" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">{{ summary.maintenance }}</p>
                    <p class="mt-1 text-xs text-gray-500">Repairs, inspection, cleaning</p>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Unavailable</p>
                        <ShieldAlert class="h-4 w-4 text-gray-700" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">{{ summary.unavailable }}</p>
                    <p class="mt-1 text-xs text-gray-500">Locked, closed, or blocked</p>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Reserved</p>
                        <Clock class="h-4 w-4 text-status-reserved" />
                    </div>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">{{ summary.reserved }}</p>
                    <p class="mt-1 text-xs text-gray-500">Non-class room use</p>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex rounded-xl bg-pup-gold-pale p-1">
                            <button
                                v-for="tab in overrideTabs"
                                :key="tab.value"
                                type="button"
                                @click="activeTab = tab.value"
                                :class="[
                                    'rounded-lg px-4 py-2 text-sm font-semibold transition',
                                    activeTab === tab.value ? 'bg-pup-maroon text-white shadow-sm' : 'text-pup-maroon hover:bg-white/60',
                                ]"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-[1fr_180px_180px] xl:w-[760px]">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500">Search</label>
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search room, status, reason, or creator..."
                                    class="h-10 w-full rounded-lg border border-gray-200 bg-white pl-9 pr-3 text-sm text-gray-700 placeholder-gray-400 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500">Room</label>
                            <div class="relative">
                                <select
                                    v-model="selectedRoom"
                                    class="h-10 w-full appearance-none rounded-lg border border-gray-200 bg-white px-3 pr-9 text-sm text-gray-700 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                >
                                    <option value="all">All rooms</option>
                                    <option v-for="room in rooms" :key="room.id" :value="String(room.id)">{{ room.code }}</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500">Status</label>
                            <div class="relative">
                                <select
                                    v-model="selectedStatus"
                                    class="h-10 w-full appearance-none rounded-lg border border-gray-200 bg-white px-3 pr-9 text-sm text-gray-700 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                >
                                    <option value="all">All statuses</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="unavailable">Unavailable</option>
                                    <option value="reserved">Reserved</option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Room Override Board</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Active overrides win over schedule exceptions and regular weekly schedules.</p>
                    </div>
                    <span :class="['inline-flex rounded-full border px-3 py-1 text-xs font-semibold', filterBadgeClass(activeTab)]">
                        {{ filterLabel(activeTab) }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-pup-maroon-deep text-left text-xs font-semibold uppercase tracking-wider text-white">
                            <tr>
                                <th class="px-5 py-3">Room</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Reason</th>
                                <th class="px-5 py-3">Starts</th>
                                <th class="px-5 py-3">Ends</th>
                                <th class="px-5 py-3">State</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white text-sm">
                            <tr v-for="item in paginatedOverrides" :key="item.id" class="transition hover:bg-pup-off-white">
                                <td class="px-5 py-4">
                                    <div class="font-mono text-xs font-semibold text-pup-maroon">{{ item.room_code }}</div>
                                    <div class="mt-1 max-w-[220px] truncate text-xs text-gray-500">{{ item.room_name }}</div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span :class="['inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold', statusBadgeClass(item.status)]">
                                        {{ statusLabel(item.status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="max-w-[280px] truncate text-gray-700">{{ item.reason || 'No reason provided' }}</p>
                                    <p class="mt-1 text-xs text-gray-400">{{ durationLabel(item) }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-gray-600">{{ formatDateTime(item.starts_at) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-gray-600">{{ formatDateTime(item.ends_at) }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span :class="['inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold', stateBadgeClass(overrideState(item))]">
                                        {{ stateLabel(overrideState(item)) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="openView(item)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50"
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                            View
                                        </button>
                                        <button
                                            v-if="overrideState(item) !== 'history'"
                                            type="button"
                                            @click="openClear(item)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50"
                                        >
                                            <Power class="h-3.5 w-3.5" />
                                            Clear
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredOverrides.length === 0">
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                                        <Archive class="h-6 w-6 text-gray-400" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">No room overrides found</p>
                                    <p class="mt-1 text-sm text-gray-500">Try changing the tab or filters.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-gray-100 bg-gray-50/50 px-5 py-4">
                    
                    <div class="flex flex-1 justify-between sm:hidden">
                        <button
                            @click="prevPage"
                            :disabled="currentPage === 1"
                            class="flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-gray-600 transition-colors hover:border-pup-maroon hover:bg-pup-maroon-pale hover:text-pup-maroon disabled:pointer-events-none disabled:opacity-50 shadow-sm"
                        >
                            Previous
                        </button>
                        <button
                            @click="nextPage"
                            :disabled="currentPage === totalPages"
                            class="flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-gray-600 transition-colors hover:border-pup-maroon hover:bg-pup-maroon-pale hover:text-pup-maroon disabled:pointer-events-none disabled:opacity-50 shadow-sm"
                        >
                            Next
                        </button>
                    </div>

                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">
                                Showing
                                <span class="font-bold text-gray-800">{{ (currentPage - 1) * itemsPerPage + 1 }}</span>
                                to
                                <span class="font-bold text-gray-800">{{ Math.min(currentPage * itemsPerPage, filteredOverrides.length) }}</span>
                                of
                                <span class="font-bold text-gray-800">{{ filteredOverrides.length }}</span>
                                entries
                            </p>
                        </div>
                        <div>
                            <nav class="flex items-center gap-2" aria-label="Pagination">
                                <button
                                    @click="prevPage"
                                    :disabled="currentPage === 1"
                                    class="group flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-400 transition-all hover:border-pup-maroon hover:bg-pup-maroon-pale hover:text-pup-maroon hover:shadow-sm disabled:pointer-events-none disabled:opacity-40"
                                >
                                    <span class="sr-only">Previous</span>
                                    <ChevronLeft class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
                                </button>

                                <div class="flex h-8 items-center justify-center rounded-lg border border-gray-200 bg-white px-3.5 shadow-sm">
                                    <span class="text-xs font-medium text-gray-500">
                                        Page <span class="font-black text-pup-maroon">{{ currentPage }}</span> of {{ totalPages }}
                                    </span>
                                </div>

                                <button
                                    @click="nextPage"
                                    :disabled="currentPage === totalPages"
                                    class="group flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-400 transition-all hover:border-pup-maroon hover:bg-pup-maroon-pale hover:text-pup-maroon hover:shadow-sm disabled:pointer-events-none disabled:opacity-40"
                                >
                                    <span class="sr-only">Next</span>
                                    <ChevronRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <RoomStatusViewModal
            v-if="activeModal === 'create' || activeModal === 'view'"
            :mode="activeModal === 'create' ? 'create' : 'view'"
            :is-editing="isDetailsEditing"
            :selected-override="selectedOverride"
            :rooms="rooms"
            :form="form"
            :override-statuses="overrideStatuses"
            @close="closeModal"
            @cancel="cancelOrCloseModal"
            @enable-edit="enableDetailsEdit"
            @save="saveOverride"
        />

        <RoomStatusClearModal
            v-if="activeModal === 'clear'"
            :selected-override="selectedOverride"
            @close="closeModal"
            @cancel="closeModal"
            @confirm="clearOverride"
        />
    </AppLayout>
</template>