<script setup lang="ts">
import { ClipboardList, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { RoomUsageLogItem } from './type';

const props = defineProps<{
    logs: RoomUsageLogItem[];
}>();

const BORROW_TYPE_LABEL: Record<string, string> = {
    regular: 'Regular Class',
    room_change: 'Room Change',
    special_class: 'Special Class',
    makeup_class: 'Makeup Class',
    daily_operation: 'Daily Operation',
};

const STATUS_LABEL: Record<string, string> = {
    occupied: 'In Use',
    completed: 'Completed',
};

const BORROW_TYPE_BADGE: Record<string, string> = {
    regular: 'bg-pup-gold-pale text-pup-maroon-deep',
    room_change: 'border border-pup-maroon/30 bg-white text-pup-maroon',
    special_class: 'bg-status-notice-bg text-status-notice',
    makeup_class: 'bg-status-warning-bg text-status-warning',
    daily_operation: 'border border-pup-maroon/30 bg-white text-pup-maroon',
};

const STATUS_BADGE: Record<string, string> = {
    occupied: 'bg-status-occupied-bg text-status-occupied',
    completed: 'bg-status-available-bg text-status-available',
};

// --- Client-Side Pagination Logic ---
const currentPage = ref(1);
const itemsPerPage = 10;

const totalPages = computed(() => Math.ceil(props.logs.length / itemsPerPage));

const paginatedLogs = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return props.logs.slice(start, end);
});

// Reset to page 1 if the underlying logs data completely changes
watch(() => props.logs.length, () => {
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

function normalizeTimeValue(value?: string | null): string | null {
    if (!value) return null;

    const directTime = value.match(/^(\d{2}:\d{2})/);
    if (directTime) return directTime[1];

    const embeddedTime = value.match(/[T\s](\d{2}:\d{2})/);
    if (embeddedTime) return embeddedTime[1];

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;

    return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
}

function parseMinutes(value?: string | null): number | null {
    const normalized = normalizeTimeValue(value);
    if (!normalized) return null;

    const [hour, minute] = normalized.split(':').map(Number);
    if (Number.isNaN(hour) || Number.isNaN(minute)) return null;

    return hour * 60 + minute;
}

function formatDate(value: string): string {
    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(value?: string | null): string {
    const normalized = normalizeTimeValue(value);
    if (!normalized) return '—';

    const [hour, minute] = normalized.split(':').map(Number);
    const period = hour < 12 ? 'AM' : 'PM';

    return `${hour % 12 || 12}:${minute.toString().padStart(2, '0')} ${period}`;
}

function formatTimeRange(start?: string | null, end?: string | null): string {
    return `${formatTime(start)} – ${formatTime(end)}`;
}

function durationLabel(log: RoomUsageLogItem): string {
    const start = parseMinutes(log.actual_start) ?? parseMinutes(log.expected_start);
    const end = parseMinutes(log.actual_end) ?? parseMinutes(log.expected_end);

    if (start === null || end === null || end <= start) {
        return log.status === 'occupied' ? 'In progress' : '—';
    }

    const minutes = end - start;
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    if (hours === 0) return `${remainder}m`;
    if (remainder === 0) return `${hours}h`;

    return `${hours}h ${remainder}m`;
}

function roomLabel(log: RoomUsageLogItem): string {
    return log.room_code || `Room ${log.room_id}`;
}

function borrowTypeLabel(log: RoomUsageLogItem): string {
    const type = log.borrow_type ?? 'daily_operation';

    return BORROW_TYPE_LABEL[type] ?? 'Borrowed Room';
}

function borrowTypeBadgeClass(log: RoomUsageLogItem): string {
    const type = log.borrow_type ?? 'daily_operation';

    return BORROW_TYPE_BADGE[type] ?? BORROW_TYPE_BADGE.daily_operation;
}

function statusBadgeClass(log: RoomUsageLogItem): string {
    return STATUS_BADGE[log.status] ?? 'bg-gray-100 text-gray-600';
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">
            <ClipboardList class="h-4 w-4 text-pup-maroon" />
            Valid classroom borrowing records
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-pup-maroon text-xs uppercase tracking-wide text-white">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Date</th>
                        <th class="px-4 py-3 text-left font-semibold">Room</th>
                        <th class="px-4 py-3 text-left font-semibold">Details</th>
                        <th class="px-4 py-3 text-left font-semibold">Expected Time</th>
                        <th class="px-4 py-3 text-left font-semibold">Actual Borrowing</th>
                        <th class="px-4 py-3 text-left font-semibold">Type</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="log in paginatedLogs" :key="log.id" class="transition hover:bg-pup-maroon-pale/30">
                        <td class="whitespace-nowrap px-4 py-3 align-top font-semibold text-gray-700">
                            {{ formatDate(log.usage_date) }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 align-top">
                            <span class="inline-flex rounded-lg bg-pup-maroon-pale px-2.5 py-1 text-xs font-black text-pup-maroon">
                                {{ roomLabel(log) }}
                            </span>
                        </td>

                        <td class="min-w-[320px] px-4 py-3 align-top">
                            <div class="font-bold text-gray-900">{{ log.subject_code }} · {{ log.subject_title }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                                <span class="font-semibold text-gray-600">{{ log.section }}</span>
                                <span class="text-gray-300">•</span>
                                <span>{{ log.instructor_name || 'No instructor listed' }}</span>
                            </div>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 align-top text-gray-700">
                            <div class="font-semibold">{{ formatTimeRange(log.expected_start, log.expected_end) }}</div>
                            <div class="text-xs text-gray-500">Scheduled slot</div>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 align-top text-gray-700">
                            <div class="font-semibold">{{ formatTimeRange(log.actual_start, log.actual_end) }}</div>
                            <div class="text-xs text-gray-500">Duration: {{ durationLabel(log) }}</div>
                        </td>

                        <td class="px-4 py-3 align-top">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="borrowTypeBadgeClass(log)">
                                {{ borrowTypeLabel(log) }}
                            </span>
                        </td>

                        <td class="px-4 py-3 align-top">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="statusBadgeClass(log)">
                                {{ STATUS_LABEL[log.status] ?? log.status }}
                            </span>
                        </td>
                    </tr>

                    <tr v-if="props.logs.length === 0">
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="mx-auto flex max-w-md flex-col items-center gap-2">
                                <div class="rounded-full bg-pup-maroon-pale p-3 text-pup-maroon">
                                    <ClipboardList class="h-5 w-5" />
                                </div>
                                <div class="text-sm font-bold text-gray-800">No valid borrowing records found</div>
                                <p class="text-xs leading-relaxed text-gray-500">
                                    Start or complete a class in Daily Operations to generate a valid room borrowing record.
                                </p>
                            </div>
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
                        <span class="font-bold text-gray-800">{{ Math.min(currentPage * itemsPerPage, props.logs.length) }}</span>
                        of
                        <span class="font-bold text-gray-800">{{ props.logs.length }}</span>
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
</template>