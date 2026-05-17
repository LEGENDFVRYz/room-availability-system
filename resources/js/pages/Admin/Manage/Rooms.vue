<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import RoomModal, { type Room } from './Modals/RoomModal.vue';
import RoomDeleteModal from './Modals/RoomDeleteModal.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Building2, Settings2, Search, Plus, ChevronDown } from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps<{ rooms: Room[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Manage',    href: '/admin/manage/rooms' },
    { title: 'Rooms',     href: '/admin/manage/rooms' },
];

const pageheader: PageHeader = {
    title: 'Manage Rooms',
    desc: 'Manage physical room identities, types, and base configurations.',
};

const manageTabs = [
    { label: 'Manage Rooms',  href: '/admin/manage/rooms',   icon: Building2 },
    { label: 'Manage Config', href: '/admin/manage/configs',  icon: Settings2 },
];

// ── Filters ───────────────────────────────────────────────────────────────────

const searchQuery  = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const typeFilter   = ref('all');

const TYPE_OPTIONS = [
    { value: 'all',          label: 'All Types' },
    { value: 'classroom',    label: 'Classroom' },
    { value: 'laboratory',   label: 'Laboratory' },
    { value: 'office',       label: 'Office' },
    { value: 'special_room', label: 'Special Room' },
    { value: 'other',        label: 'Other' },
];

const STATUS_OPTIONS = [
    { value: 'all',      label: 'All Statuses' },
    { value: 'active',   label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const filteredRooms = computed(() =>
    props.rooms.filter(room => {
        const q = searchQuery.value.toLowerCase();
        const matchesSearch =
            room.code.toLowerCase().includes(q) ||
            room.name.toLowerCase().includes(q) ||
            room.type_label.toLowerCase().includes(q);
        const matchesStatus =
            statusFilter.value === 'all' ||
            (statusFilter.value === 'active'   &&  room.is_active) ||
            (statusFilter.value === 'inactive' && !room.is_active);
        const matchesType =
            typeFilter.value === 'all' || room.room_type === typeFilter.value;
        return matchesSearch && matchesStatus && matchesType;
    })
);

// ── Modal state ───────────────────────────────────────────────────────────────

const selectedRoom    = ref<Room | null>(null);
const showRoomModal   = ref(false);
const showDeleteModal = ref(false);

function openCreate() {
    selectedRoom.value  = null;
    showRoomModal.value = true;
}

function openView(room: Room) {
    selectedRoom.value  = room;
    showRoomModal.value = true;
}

function openDelete(room: Room) {
    selectedRoom.value    = room;
    showDeleteModal.value = true;
}

function closeAll() {
    showRoomModal.value   = false;
    showDeleteModal.value = false;
    selectedRoom.value    = null;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const TYPE_BADGE: Record<string, string> = {
    classroom:    'bg-blue-50 text-blue-700 border-blue-200',
    laboratory:   'bg-violet-50 text-violet-700 border-violet-200',
    office:       'bg-orange-50 text-orange-700 border-orange-200',
    special_room: 'bg-teal-50 text-teal-700 border-teal-200',
    other:        'bg-gray-100 text-gray-600 border-gray-200',
};
</script>

<template>
    <Head title="Manage Rooms" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">

        <!-- Tab switcher -->
        <div class="mb-6">
            <PillTabs :tabs="manageTabs" />
        </div>

        <!-- Controls row -->
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">

                <!-- Search -->
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search rooms…"
                        class="h-9 w-52 rounded-lg border border-gray-200 bg-white pl-9 pr-3 text-sm text-gray-700 placeholder-gray-400 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                    />
                </div>

                <!-- Type filter -->
                <div class="relative">
                    <select
                        v-model="typeFilter"
                        class="h-9 appearance-none rounded-lg border border-gray-200 bg-white pl-3 pr-8 text-sm text-gray-700 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                    >
                        <option v-for="t in TYPE_OPTIONS" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                </div>

                <!-- Status filter -->
                <div class="relative">
                    <select
                        v-model="statusFilter"
                        class="h-9 appearance-none rounded-lg border border-gray-200 bg-white pl-3 pr-8 text-sm text-gray-700 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                    >
                        <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                </div>
            </div>

            <!-- Add Room -->
            <button
                @click="openCreate"
                class="flex h-9 items-center gap-2 rounded-lg bg-pup-maroon px-4 text-sm font-medium text-white shadow-sm transition-colors hover:bg-pup-maroon-light"
            >
                <Plus class="h-4 w-4" />
                Add Room
            </button>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-pup-maroon-deep text-left text-white">
                            <th class="px-5 py-3.5 font-semibold tracking-wide">Code</th>
                            <th class="px-5 py-3.5 font-semibold tracking-wide">Room Name</th>
                            <th class="px-5 py-3.5 font-semibold tracking-wide">Type</th>
                            <th class="px-5 py-3.5 text-center font-semibold tracking-wide">Floor</th>
                            <th class="px-5 py-3.5 text-center font-semibold tracking-wide">Capacity</th>
                            <th class="px-5 py-3.5 text-center font-semibold tracking-wide">Status</th>
                            <th class="px-5 py-3.5 text-center font-semibold tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="room in filteredRooms"
                            :key="room.id"
                            class="transition-colors hover:bg-gray-50/70"
                            :class="{ 'opacity-60': !room.is_active }"
                        >
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-pup-maroon">
                                {{ room.code }}
                            </td>
                            <td class="px-5 py-3.5 font-medium text-gray-800">{{ room.name }}</td>
                            <td class="px-5 py-3.5">
                                <span :class="['inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium', TYPE_BADGE[room.room_type] ?? TYPE_BADGE.other]">
                                    {{ room.type_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center text-gray-600">{{ room.floor ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-center text-gray-600">{{ room.capacity ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <span
                                    v-if="room.is_active"
                                    class="inline-flex items-center rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700"
                                >
                                    Active
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500"
                                >
                                    Inactive
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-center gap-2">
                                    <button
                                        @click="openView(room)"
                                        class="rounded-lg border border-pup-maroon px-3 py-1.5 text-xs font-medium text-pup-maroon transition hover:bg-pup-maroon hover:text-white"
                                    >
                                        View
                                    </button>
                                    <button
                                        v-if="room.is_active"
                                        @click="openDelete(room)"
                                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-500 transition hover:bg-red-500 hover:text-white"
                                    >
                                        Deactivate
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="filteredRooms.length === 0">
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-400">
                                No rooms match your filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 bg-gray-50/50 px-5 py-3">
                <span class="text-xs text-gray-500">
                    Showing <strong>{{ filteredRooms.length }}</strong> of <strong>{{ rooms.length }}</strong> rooms
                </span>
                <span class="text-xs italic text-gray-400">
                    Live room status is determined by the backend
                </span>
            </div>
        </div>

    </AppLayout>

    <!-- Unified Room Modal (create / view / edit) -->
    <RoomModal
        v-if="showRoomModal"
        :room="selectedRoom"
        @close="closeAll"
    />

    <!-- Deactivate confirmation -->
    <RoomDeleteModal
        v-if="showDeleteModal && selectedRoom"
        :room="selectedRoom"
        @close="closeAll"
    />

</template>
