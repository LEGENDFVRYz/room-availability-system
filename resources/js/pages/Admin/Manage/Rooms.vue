<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Building2, Settings2, Search, Plus, ChevronDown } from 'lucide-vue-next';
import { ref, computed } from 'vue';

type RoomStatus = 'Available' | 'Occupied' | 'Reserved' | 'Maintenance' | 'Unavailable';
type RoomType   = 'Computer Lab' | 'Lecture Room' | 'Faculty Room' | 'Conference Room';

interface Room {
    id: string;
    name: string;
    type: RoomType;
    building: string;
    floor: number;
    capacity: number;
    status: RoomStatus;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Manage',    href: '/admin/manage/rooms' },
    { title: 'Rooms',     href: '/admin/manage/rooms' },
];

const pageheader: PageHeader = {
    title: 'Manage Rooms',
    desc: 'View and manage department rooms · CPE Department',
};

const rooms: Room[] = [
    { id: 'CPE-LAB-01', name: 'Computer Laboratory 1', type: 'Computer Lab',    building: 'Engineering Building', floor: 2, capacity: 40, status: 'Available'   },
    { id: 'CPE-LAB-02', name: 'Computer Laboratory 2', type: 'Computer Lab',    building: 'Engineering Building', floor: 2, capacity: 40, status: 'Occupied'    },
    { id: 'CPE-LAB-03', name: 'Computer Laboratory 3', type: 'Computer Lab',    building: 'Engineering Building', floor: 3, capacity: 35, status: 'Available'   },
    { id: 'CPE-LAB-04', name: 'Computer Laboratory 4', type: 'Computer Lab',    building: 'Engineering Building', floor: 3, capacity: 35, status: 'Maintenance' },
    { id: 'CPE-LAB-05', name: 'Computer Laboratory 5', type: 'Computer Lab',    building: 'Engineering Building', floor: 4, capacity: 30, status: 'Reserved'    },
    { id: 'CPE-LEC-01', name: 'Lecture Room 1',        type: 'Lecture Room',    building: 'Engineering Building', floor: 1, capacity: 50, status: 'Occupied'    },
    { id: 'CPE-LEC-02', name: 'Lecture Room 2',        type: 'Lecture Room',    building: 'Engineering Building', floor: 1, capacity: 50, status: 'Available'   },
    { id: 'CPE-LEC-03', name: 'Lecture Room 3',        type: 'Lecture Room',    building: 'Engineering Building', floor: 2, capacity: 45, status: 'Unavailable' },
    { id: 'CPE-FAC-01', name: 'Faculty Room',          type: 'Faculty Room',    building: 'Engineering Building', floor: 2, capacity: 20, status: 'Available'   },
    { id: 'CPE-CON-01', name: 'Conference Room',       type: 'Conference Room', building: 'Engineering Building', floor: 3, capacity: 15, status: 'Reserved'    },
];

const searchQuery  = ref('');
const statusFilter = ref<RoomStatus | 'All'>('All');

const filteredRooms = computed(() =>
    rooms.filter(room => {
        const q = searchQuery.value.toLowerCase();
        const matchesSearch =
            room.id.toLowerCase().includes(q) ||
            room.name.toLowerCase().includes(q) ||
            room.type.toLowerCase().includes(q);
        const matchesStatus = statusFilter.value === 'All' || room.status === statusFilter.value;
        return matchesSearch && matchesStatus;
    })
);

const statusOptions: (RoomStatus | 'All')[] = [
    'All', 'Available', 'Occupied', 'Reserved', 'Maintenance', 'Unavailable',
];

function statusBadgeClass(status: RoomStatus) {
    const map: Record<RoomStatus, string> = {
        Available:   'bg-green-100 text-green-700 border border-green-200',
        Occupied:    'bg-red-100 text-red-700 border border-red-200',
        Reserved:    'bg-amber-100 text-amber-700 border border-amber-200',
        Maintenance: 'bg-gray-100 text-gray-500 border border-gray-200',
        Unavailable: 'bg-slate-100 text-slate-500 border border-slate-200',
    };
    return map[status];
}

function typeBadgeClass(type: RoomType) {
    const map: Record<RoomType, string> = {
        'Computer Lab':    'bg-blue-50 text-blue-700 border border-blue-200',
        'Lecture Room':    'bg-violet-50 text-violet-700 border border-violet-200',
        'Faculty Room':    'bg-orange-50 text-orange-700 border border-orange-200',
        'Conference Room': 'bg-teal-50 text-teal-700 border border-teal-200',
    };
    return map[type];
}

function floorLabel(floor: number) {
    const suffix = floor === 1 ? 'st' : floor === 2 ? 'nd' : floor === 3 ? 'rd' : 'th';
    return `${floor}${suffix} Floor`;
}
</script>

<template>
    <Head title="Manage Rooms" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">

        <!-- Tab switcher -->
        <div class="mb-6">
            <PillTabs :tabs="[
                { label: 'Manage Rooms',  href: '/admin/manage/rooms',   icon: Building2 },
                { label: 'Manage Config', href: '/admin/manage/configs',  icon: Settings2 },
            ]" />
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
                        class="h-9 w-56 rounded-lg border border-gray-200 bg-white pl-9 pr-3 text-sm text-gray-700 placeholder-gray-400 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                    />
                </div>

                <!-- Status filter -->
                <div class="relative">
                    <select
                        v-model="statusFilter"
                        class="h-9 appearance-none rounded-lg border border-gray-200 bg-white pl-3 pr-8 text-sm text-gray-700 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                    >
                        <option v-for="s in statusOptions" :key="s" :value="s">
                            {{ s === 'All' ? 'All Statuses' : s }}
                        </option>
                    </select>
                    <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                </div>
            </div>

            <!-- Add Room -->
            <button
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
                            <th class="px-5 py-3.5 font-semibold tracking-wide">Room ID</th>
                            <th class="px-5 py-3.5 font-semibold tracking-wide">Room Name</th>
                            <th class="px-5 py-3.5 font-semibold tracking-wide">Type</th>
                            <th class="px-5 py-3.5 font-semibold tracking-wide">Building</th>
                            <th class="px-5 py-3.5 font-semibold tracking-wide">Floor</th>
                            <th class="px-5 py-3.5 text-center font-semibold tracking-wide">Capacity</th>
                            <th class="px-5 py-3.5 text-center font-semibold tracking-wide">Status</th>
                            <th class="px-5 py-3.5 text-center font-semibold tracking-wide">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="room in filteredRooms"
                            :key="room.id"
                            class="transition-colors hover:bg-gray-50/70"
                        >
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-pup-maroon">
                                {{ room.id }}
                            </td>
                            <td class="px-5 py-3.5 font-medium text-gray-800">{{ room.name }}</td>
                            <td class="px-5 py-3.5">
                                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', typeBadgeClass(room.type)]">
                                    {{ room.type }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600">{{ room.building }}</td>
                            <td class="px-5 py-3.5 text-gray-600">{{ floorLabel(room.floor) }}</td>
                            <td class="px-5 py-3.5 text-center text-gray-600">{{ room.capacity }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', statusBadgeClass(room.status)]">
                                    {{ room.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <button class="rounded-lg border border-pup-maroon px-3 py-1.5 text-xs font-medium text-pup-maroon transition-colors hover:bg-pup-maroon hover:text-white">
                                    View &amp; Edit
                                </button>
                            </td>
                        </tr>

                        <tr v-if="filteredRooms.length === 0">
                            <td colspan="8" class="px-5 py-12 text-center text-sm text-gray-400">
                                No rooms match your search criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer row -->
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
</template>
