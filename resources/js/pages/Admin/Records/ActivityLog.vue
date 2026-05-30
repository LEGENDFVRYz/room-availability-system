<script setup lang="ts">
import PillTabs from '@/components/PillTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
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
    return props.logs.filter((log) => {
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
                :rooms="rooms"
                :result-count="filteredLogs.length"
                @update:filters="filters = $event"
                @reset="resetFilters"
            />

            <ActivityLogTable :logs="filteredLogs" />
        </div>
    </AppLayout>
</template>
