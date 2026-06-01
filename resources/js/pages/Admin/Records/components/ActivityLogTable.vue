<script setup lang="ts">
import { ScrollText, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { ActivityLogItem } from './type';

const props = defineProps<{
    logs: ActivityLogItem[];
}>();

const CATEGORY_LABEL: Record<string, string> = {
    cancellation: 'Cancelled Class',
    room_change: 'Room Change',
    class_request: 'Class Request',
    room_override: 'Room Override',
    daily_exception: 'Daily Exception',
    revert_action: 'Revert Action',
    system: 'System Action',
};

const CATEGORY_BADGE: Record<string, string> = {
    cancellation: 'bg-status-notice-bg text-status-notice',
    room_change: 'border border-pup-maroon/30 bg-white text-pup-maroon',
    class_request: 'bg-status-warning-bg text-status-warning',
    room_override: 'bg-status-reserved-bg text-status-reserved',
    daily_exception: 'bg-pup-gold-pale text-pup-maroon-deep',
    revert_action: 'border border-gray-200 bg-gray-50 text-gray-600',
    system: 'bg-status-maintenance-bg text-status-maintenance',
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

function formatDateTime(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function adminLabel(log: ActivityLogItem): string {
    return log.admin_name || log.user_name || 'System';
}

function categoryLabel(log: ActivityLogItem): string {
    const category = log.category || 'daily_exception';

    return CATEGORY_LABEL[category] ?? titleCase(category);
}

function categoryBadgeClass(log: ActivityLogItem): string {
    const category = log.category || 'daily_exception';

    return CATEGORY_BADGE[category] ?? 'border border-gray-200 bg-gray-50 text-gray-600';
}

function roomLabel(log: ActivityLogItem): string {
    return log.room_code || (log.room_id ? `Room ${log.room_id}` : '—');
}

function activityTitle(log: ActivityLogItem): string {
    return log.title || titleCase(log.action.replaceAll('.', ' '));
}

function titleCase(value: string): string {
    return value
        .replaceAll('_', ' ')
        .split(' ')
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700">
            <ScrollText class="h-4 w-4 text-pup-maroon" />
            Admin activity history
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-pup-maroon text-xs uppercase tracking-wide text-white">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Date & Time</th>
                        <th class="px-4 py-3 text-left font-semibold">Admin</th>
                        <th class="px-4 py-3 text-left font-semibold">Activity</th>
                        <th class="px-4 py-3 text-left font-semibold">Room</th>
                        <th class="px-4 py-3 text-left font-semibold">Details</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="log in paginatedLogs" :key="log.id" class="transition hover:bg-pup-maroon-pale/30">
                        <td class="whitespace-nowrap px-4 py-3 align-top font-semibold text-gray-700">
                            {{ formatDateTime(log.created_at) }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 align-top">
                            <div class="font-bold text-gray-900">{{ adminLabel(log) }}</div>
                            <div v-if="log.ip_address" class="mt-1 text-xs text-gray-500">{{ log.ip_address }}</div>
                        </td>

                        <td class="min-w-[220px] px-4 py-3 align-top">
                            <div class="font-bold text-gray-900">{{ activityTitle(log) }}</div>
                            <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="categoryBadgeClass(log)">
                                {{ categoryLabel(log) }}
                            </span>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 align-top">
                            <span
                                class="inline-flex rounded-lg px-2.5 py-1 text-xs font-black"
                                :class="log.room_code || log.room_id ? 'bg-pup-maroon-pale text-pup-maroon' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ roomLabel(log) }}
                            </span>
                        </td>

                        <td class="min-w-[360px] px-4 py-3 align-top">
                            <div class="font-semibold leading-relaxed text-gray-800">{{ log.description }}</div>
                            <div v-if="log.details" class="mt-1 text-xs leading-relaxed text-gray-500">{{ log.details }}</div>
                            <div v-if="log.entity_type" class="mt-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400">
                                {{ log.entity_type }}<span v-if="log.entity_id"> #{{ log.entity_id }}</span>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="props.logs.length === 0">
                        <td colspan="5" class="px-4 py-12 text-center">
                            <div class="mx-auto flex max-w-md flex-col items-center gap-2">
                                <div class="rounded-full bg-pup-maroon-pale p-3 text-pup-maroon">
                                    <ScrollText class="h-5 w-5" />
                                </div>
                                <div class="text-sm font-bold text-gray-800">No admin activity found</div>
                                <p class="text-xs leading-relaxed text-gray-500">
                                    Admin cancellations, room overrides, daily exceptions, and revert actions will appear here once activity logging is connected.
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