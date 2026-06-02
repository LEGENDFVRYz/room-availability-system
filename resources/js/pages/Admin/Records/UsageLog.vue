<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { BookOpenCheck, CheckCircle2, Clock3, ScrollText, SquareActivity } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UsageLogFilters from './components/UsageLogFilters.vue';
import UsageLogTable from './components/UsageLogTable.vue';
import type { RoomOption, RoomUsageFiltersState, RoomUsageLogItem } from './components/type';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Room Usage Logs', href: '/admin/records/usage' },
];

const pageheader: PageHeader = {
    title: 'Room Usage Logs',
    desc: 'View valid classroom borrowing records generated from Daily Operations.',
};

const manageTabs = [
    { label: 'Room Usage', href: '/admin/records/usage', icon: SquareActivity },
    { label: 'Activity', href: '/admin/records/activity', icon: ScrollText },
];

const props = withDefaults(
    defineProps<{
        logs?: RoomUsageLogItem[];
        rooms?: RoomOption[];
    }>(),
    {
        logs: () => [],
        rooms: () => [],
    },
);

const filters = ref<RoomUsageFiltersState>({
    search: '',
    date_from: '',
    date_to: '',
    room_ids: [],
    borrow_type: 'all',
});

function resetFilters() {
    filters.value = {
        search: '',
        date_from: '',
        date_to: '',
        room_ids: [],
        borrow_type: 'all',
    };
}

function normalizeDateValue(value: string): string {
    if (!value) return '';

    const match = value.match(/^\d{4}-\d{2}-\d{2}/);

    return match ? match[0] : value;
}

function isValidBorrowingLog(log: RoomUsageLogItem): boolean {
    const status = String(log.status).toLowerCase();
    const source = String(log.source).toLowerCase();

    return ['occupied', 'completed'].includes(status) && ['schedule', 'schedule_exception'].includes(source);
}

function matchesSearch(log: RoomUsageLogItem, keyword: string): boolean {
    if (!keyword) return true;

    const searchable = [
        log.room_code,
        log.subject_code,
        log.subject_title,
        log.section,
        log.instructor_name,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return searchable.includes(keyword.toLowerCase());
}

function parseMinutes(value?: string | null): number | null {
    if (!value) return null;

    const directTime = value.match(/^(\d{2}):(\d{2})/);
    const embeddedTime = value.match(/[T\s](\d{2}):(\d{2})/);
    const match = directTime ?? embeddedTime;

    if (!match) return null;

    const hour = Number(match[1]);
    const minute = Number(match[2]);

    if (Number.isNaN(hour) || Number.isNaN(minute)) return null;

    return hour * 60 + minute;
}

const validBorrowingLogs = computed(() => props.logs.filter(isValidBorrowingLog));

const filteredLogs = computed(() => {
    return validBorrowingLogs.value.filter((log) => {
        const usageDate = normalizeDateValue(log.usage_date);
        const search = filters.value.search.trim();

        if (!matchesSearch(log, search)) return false;
        if (filters.value.date_from && usageDate < filters.value.date_from) return false;
        if (filters.value.date_to && usageDate > filters.value.date_to) return false;
        if (filters.value.room_ids.length > 0 && !filters.value.room_ids.includes(log.room_id)) return false;
        if (filters.value.borrow_type !== 'all' && log.borrow_type !== filters.value.borrow_type) return false;

        return true;
    });
});

const totalBorrowedMinutes = computed(() => {
    return filteredLogs.value.reduce((total, log) => {
        const start = parseMinutes(log.actual_start) ?? parseMinutes(log.expected_start);
        const end = parseMinutes(log.actual_end) ?? parseMinutes(log.expected_end);

        if (start === null || end === null || end <= start) return total;

        return total + (end - start);
    }, 0);
});

const totalHoursLabel = computed(() => {
    const minutes = totalBorrowedMinutes.value;
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    if (minutes === 0) return '0h';
    if (hours === 0) return `${remainder}m`;
    if (remainder === 0) return `${hours}h`;

    return `${hours}h ${remainder}m`;
});

const summaryCards = computed(() => [
    {
        label: 'Valid Borrowings',
        value: filteredLogs.value.length,
        note: 'Classroom use only',
        icon: BookOpenCheck,
        class: 'border-pup-maroon/20 bg-pup-maroon-pale/40 text-pup-maroon',
    },
    {
        label: 'Currently In Use',
        value: filteredLogs.value.filter((log) => log.status === 'occupied').length,
        note: 'Borrowing not yet ended',
        icon: Clock3,
        class: 'border-status-occupied/20 bg-status-occupied-bg text-status-occupied',
    },
    {
        label: 'Completed Uses',
        value: filteredLogs.value.filter((log) => log.status === 'completed').length,
        note: 'Finished borrowing records',
        icon: CheckCircle2,
        class: 'border-status-available/20 bg-status-available-bg text-status-available',
    },
    {
        label: 'Total Borrowed Time',
        value: totalHoursLabel.value,
        note: 'Based on actual/expected logs',
        icon: SquareActivity,
        class: 'border-pup-gold/30 bg-pup-gold-pale/50 text-pup-maroon-deep',
    },
]);
</script>

<template>
    <Head title="Room Usage Logs" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <div class="space-y-6">
            <PillTabs :tabs="manageTabs" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="rounded-xl border bg-white p-4 shadow-sm"
                    :class="card.class"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide opacity-70">{{ card.label }}</p>
                            <p class="mt-2 text-2xl font-black">{{ card.value }}</p>
                            <p class="mt-1 text-xs font-semibold opacity-70">{{ card.note }}</p>
                        </div>
                        <div class="rounded-full bg-white/70 p-2 shadow-sm">
                            <component :is="card.icon" class="h-5 w-5" />
                        </div>
                    </div>
                </div>
            </div>

            <UsageLogFilters
                :filters="filters"
                :rooms="props.rooms"
                :result-count="filteredLogs.length"
                export-href="/admin/records/usage/export/pdf"
                @update:filters="filters = $event"
                @reset="resetFilters"
            />

            <UsageLogTable :logs="filteredLogs" />
        </div>
    </AppLayout>
</template>
