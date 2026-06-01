<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ScrollText, SquareActivity } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ActivityLogFilters from './components/ActivityLogFilters.vue';
import ActivityLogTable from './components/ActivityLogTable.vue';
import type { ActivityLogFiltersState, ActivityLogItem, RoomOption } from './components/type';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Admin Activity Logs', href: '/admin/records/activity' },
];

const pageheader: PageHeader = {
    title: 'Admin Activity Logs',
    desc: 'Review admin decisions such as class cancellations, room overrides, room changes, and daily exceptions.',
};

const manageTabs = [
    { label: 'Room Usage', href: '/admin/records/usage', icon: SquareActivity },
    { label: 'Activity', href: '/admin/records/activity', icon: ScrollText },
];

const props = withDefaults(
    defineProps<{
        logs?: ActivityLogItem[];
        rooms?: RoomOption[];
    }>(),
    {
        logs: () => [],
        rooms: () => [],
    },
);

const demoRooms: RoomOption[] = [
    { id: 1, code: 'CEA300', name: 'Lecture Room 300' },
    { id: 2, code: 'CEA302', name: 'Lecture Room 302' },
    { id: 3, code: 'CEA313', name: 'Laboratory Room 313' },
    { id: 4, code: 'CEA316', name: 'Computer Laboratory 316' },
    { id: 5, code: 'CEA317', name: 'Microcomputer Laboratory 317' },
];

const demoLogs: ActivityLogItem[] = [
    {
        id: 1,
        created_at: '2026-06-01T07:42:00+08:00',
        admin_name: 'Admin Reyes',
        action: 'schedule.cancelled',
        category: 'cancellation',
        entity_type: 'ScheduleException',
        entity_id: 1012,
        room_id: 1,
        room_code: 'CEA300',
        title: 'Cancelled scheduled class',
        description: 'Cancelled CPE 311 for BSCOE 3-1 because the instructor was unavailable.',
        details: 'Original schedule: 8:00 AM – 10:00 AM. Reason: Instructor filed an emergency leave.',
        ip_address: '192.168.1.24',
    },
    {
        id: 2,
        created_at: '2026-06-01T08:18:00+08:00',
        admin_name: 'Admin Dela Cruz',
        action: 'schedule.room_changed',
        category: 'room_change',
        entity_type: 'ScheduleException',
        entity_id: 1013,
        room_id: 4,
        room_code: 'CEA316',
        title: 'Changed room for today',
        description: 'Moved CPE 408 from CEA302 to CEA316 for today only.',
        details: 'Affected class: Embedded Systems Laboratory, BSCOE 4-1, 10:30 AM – 1:30 PM.',
        ip_address: '192.168.1.31',
    },
    {
        id: 3,
        created_at: '2026-06-01T09:05:00+08:00',
        admin_name: 'Admin Santos',
        action: 'exception.special_class_created',
        category: 'class_request',
        entity_type: 'ScheduleException',
        entity_id: 1014,
        room_id: 5,
        room_code: 'CEA317',
        title: 'Added special class',
        description: 'Created a one-off special class for CPE 025 in CEA317.',
        details: 'Requested slot: 2:00 PM – 5:00 PM. Instructor: Engr. Ramos. Section: BSCOE 2-2.',
        ip_address: '192.168.1.18',
    },
    {
        id: 4,
        created_at: '2026-06-01T09:37:00+08:00',
        admin_name: 'Admin Reyes',
        action: 'override.created',
        category: 'room_override',
        entity_type: 'RoomOverride',
        entity_id: 220,
        room_id: 3,
        room_code: 'CEA313',
        title: 'Set room to maintenance',
        description: 'Marked CEA313 as under maintenance due to equipment inspection.',
        details: 'Override window: 9:30 AM – 12:00 PM. Reason: Projector and workstation checking.',
        ip_address: '192.168.1.24',
    },
    {
        id: 5,
        created_at: '2026-06-01T11:12:00+08:00',
        admin_name: 'Admin Dela Cruz',
        action: 'operation.reverted_start',
        category: 'revert_action',
        entity_type: 'RoomUsageLog',
        entity_id: 3301,
        room_id: 2,
        room_code: 'CEA302',
        title: 'Reverted class start',
        description: 'Reverted the started status of CPE 302 after it was marked started by mistake.',
        details: 'The class was returned to pending state and actual start time was cleared.',
        ip_address: '192.168.1.31',
    },
    {
        id: 6,
        created_at: '2026-06-01T12:25:00+08:00',
        admin_name: 'Admin Santos',
        action: 'override.cleared',
        category: 'room_override',
        entity_type: 'RoomOverride',
        entity_id: 220,
        room_id: 3,
        room_code: 'CEA313',
        title: 'Cleared room override',
        description: 'Cleared the maintenance override for CEA313 after inspection was completed.',
        details: 'Room became available again based on the normal schedule priority chain.',
        ip_address: '192.168.1.18',
    },
    {
        id: 7,
        created_at: '2026-06-01T13:46:00+08:00',
        admin_name: 'System',
        action: 'system.auto_cancelled',
        category: 'system',
        entity_type: 'ScheduleException',
        entity_id: 1015,
        room_id: 5,
        room_code: 'CEA317',
        title: 'Auto-cancelled unclaimed class',
        description: 'Auto-cancelled a pending makeup class because it was not claimed within the grace period.',
        details: 'The slot was released so the room could be reclaimed by another valid request.',
        ip_address: null,
    },
];

const displayRooms = computed(() => (props.rooms.length > 0 ? props.rooms : demoRooms));
const displayLogs = computed(() => (props.logs.length > 0 ? props.logs : demoLogs));

const filters = ref<ActivityLogFiltersState>({
    search: '',
    date_from: '',
    date_to: '',
    room_ids: [],
    category: 'all',
});

function resetFilters() {
    filters.value = {
        search: '',
        date_from: '',
        date_to: '',
        room_ids: [],
        category: 'all',
    };
}

function normalizeDateValue(value: string): string {
    if (!value) return '';

    const dateMatch = value.match(/^\d{4}-\d{2}-\d{2}/);

    if (dateMatch) return dateMatch[0];

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return value;

    return date.toISOString().slice(0, 10);
}

function matchesSearch(log: ActivityLogItem, keyword: string): boolean {
    if (!keyword) return true;

    const searchable = [
        log.admin_name,
        log.user_name,
        log.action,
        log.category,
        log.entity_type,
        log.room_code,
        log.title,
        log.description,
        log.details,
        log.ip_address,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return searchable.includes(keyword.toLowerCase());
}

const filteredLogs = computed(() => {
    return displayLogs.value.filter((log) => {
        const createdDate = normalizeDateValue(log.created_at);
        const search = filters.value.search.trim();

        if (!matchesSearch(log, search)) return false;
        if (filters.value.date_from && createdDate < filters.value.date_from) return false;
        if (filters.value.date_to && createdDate > filters.value.date_to) return false;
        if (filters.value.room_ids.length > 0 && (!log.room_id || !filters.value.room_ids.includes(log.room_id))) return false;
        if (filters.value.category !== 'all' && log.category !== filters.value.category) return false;

        return true;
    });
});
</script>

<template>
    <Head title="Admin Activity Logs" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <div class="space-y-6">
            <PillTabs :tabs="manageTabs" />

            <ActivityLogFilters
                :filters="filters"
                :rooms="displayRooms"
                :result-count="filteredLogs.length"
                @update:filters="filters = $event"
                @reset="resetFilters"
            />

            <ActivityLogTable :logs="filteredLogs" />
        </div>
    </AppLayout>
</template>
