<script setup lang="ts">
import { ScrollText } from 'lucide-vue-next';
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
                    <tr v-for="log in props.logs" :key="log.id" class="transition hover:bg-pup-maroon-pale/30">
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
    </div>
</template>
