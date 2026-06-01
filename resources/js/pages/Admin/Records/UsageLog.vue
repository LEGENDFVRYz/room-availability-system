<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { BookOpenCheck, CheckCircle2, Clock3, Info, ScrollText, SquareActivity } from 'lucide-vue-next';
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

const demoRooms: RoomOption[] = [
    { id: 1, code: 'CPE 301', name: 'Lecture Room' },
    { id: 2, code: 'CPE 302', name: 'Lecture Room' },
    { id: 3, code: 'CPE 317', name: 'Microcomputer Laboratory' },
    { id: 4, code: 'CPE 319', name: 'Electronics Laboratory' },
];

const demoLogs: RoomUsageLogItem[] = [
    {
        id: 1,
        usage_date: '2026-06-01',
        source: 'schedule',
        schedule_id: 14,
        schedule_exception_id: null,
        room_id: 1,
        room_code: 'CPE 301',
        room_name: 'Lecture Room',
        borrow_type: 'regular',
        subject_code: 'CPE 411',
        subject_title: 'Embedded Systems',
        section: 'BSCpE 4-1',
        instructor_name: 'Engr. Maria Santos',
        status: 'completed',
        expected_start: '07:30',
        expected_end: '10:30',
        actual_start: '07:35',
        actual_end: '10:20',
    },
    {
        id: 2,
        usage_date: '2026-06-01',
        source: 'schedule_exception',
        schedule_id: 22,
        schedule_exception_id: 8,
        room_id: 3,
        room_code: 'CPE 317',
        room_name: 'Microcomputer Laboratory',
        borrow_type: 'room_change',
        subject_code: 'CPE 323',
        subject_title: 'Microprocessors',
        section: 'BSCpE 3-2',
        instructor_name: 'Prof. Daniel Reyes',
        status: 'completed',
        expected_start: '10:30',
        expected_end: '13:30',
        actual_start: '10:40',
        actual_end: '13:15',
    },
    {
        id: 3,
        usage_date: '2026-06-01',
        source: 'schedule_exception',
        schedule_id: null,
        schedule_exception_id: 11,
        room_id: 4,
        room_code: 'CPE 319',
        room_name: 'Electronics Laboratory',
        borrow_type: 'special_class',
        subject_code: 'CPE 214',
        subject_title: 'Logic Circuits Laboratory',
        section: 'BSCpE 2-1',
        instructor_name: 'Engr. Camille Dela Cruz',
        status: 'occupied',
        expected_start: '14:00',
        expected_end: '17:00',
        actual_start: '14:05',
        actual_end: null,
    },
    {
        id: 4,
        usage_date: '2026-05-31',
        source: 'schedule_exception',
        schedule_id: null,
        schedule_exception_id: 15,
        room_id: 2,
        room_code: 'CPE 302',
        room_name: 'Lecture Room',
        borrow_type: 'makeup_class',
        subject_code: 'CPE 312',
        subject_title: 'Data Communications',
        section: 'BSCpE 3-1',
        instructor_name: 'Dr. Adrian Lim',
        status: 'completed',
        expected_start: '08:00',
        expected_end: '11:00',
        actual_start: '08:00',
        actual_end: '10:50',
    },
];

const displayRooms = computed(() => (props.rooms.length > 0 ? props.rooms : demoRooms));
const displayLogs = computed(() => (props.logs.length > 0 ? props.logs : demoLogs));
const isShowingDemoData = computed(() => props.logs.length === 0);

const filters = ref<RoomUsageFiltersState>({
    search: '',
    date_from: '',
    date_to: '',
    room_ids: [],
    source: 'all',
});

function resetFilters() {
    filters.value = {
        search: '',
        date_from: '',
        date_to: '',
        room_ids: [],
        source: 'all',
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

const validBorrowingLogs = computed(() => displayLogs.value.filter(isValidBorrowingLog));

const filteredLogs = computed(() => {
    return validBorrowingLogs.value.filter((log) => {
        const usageDate = normalizeDateValue(log.usage_date);
        const search = filters.value.search.trim();

        if (!matchesSearch(log, search)) return false;
        if (filters.value.date_from && usageDate < filters.value.date_from) return false;
        if (filters.value.date_to && usageDate > filters.value.date_to) return false;
        if (filters.value.room_ids.length > 0 && !filters.value.room_ids.includes(log.room_id)) return false;
        if (filters.value.source !== 'all' && log.source !== filters.value.source) return false;

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

            <div class="rounded-xl border border-pup-gold/40 bg-pup-gold-pale/40 p-4 text-sm text-pup-maroon-deep">
                <div class="flex gap-3">
                    <Info class="mt-0.5 h-4 w-4 shrink-0 text-pup-maroon" />
                    <div>
                        <p class="font-bold">This page only shows valid room borrowing records.</p>
                        <p class="mt-1 text-xs leading-relaxed text-pup-maroon/80">
                            Cancelled classes, unclaimed classes, blocked slots, maintenance, unavailable rooms, and reserved-only override records are excluded so this page stays focused on actual classroom use.
                        </p>
                        <p v-if="isShowingDemoData" class="mt-2 inline-flex rounded-full bg-white/70 px-2.5 py-1 text-[11px] font-bold text-pup-maroon shadow-sm">
                            Preview mode: showing sample borrowing records until backend data is connected.
                        </p>
                    </div>
                </div>
            </div>

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
                :rooms="displayRooms"
                :result-count="filteredLogs.length"
                @update:filters="filters = $event"
                @reset="resetFilters"
            />

            <UsageLogTable :logs="filteredLogs" />
        </div>
    </AppLayout>
</template>
