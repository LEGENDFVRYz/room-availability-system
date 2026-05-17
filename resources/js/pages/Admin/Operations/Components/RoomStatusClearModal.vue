<script setup lang="ts">
import { ShieldAlert, X } from 'lucide-vue-next';

type OverrideStatus = 'maintenance' | 'unavailable' | 'reserved';
type OverrideState = 'active' | 'upcoming' | 'history';

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

defineProps<{
    selectedOverride: RoomOverrideItem | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'cancel'): void;
    (e: 'confirm'): void;
}>();

function parseDate(value: string): Date {
    return new Date(value);
}

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
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4 backdrop-blur-[2px]"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between bg-pup-maroon-deep px-6 py-5">
                    <div>
                        <h2 class="text-[17px] font-semibold text-white">Clear Room Override</h2>
                        <p class="mt-0.5 text-xs text-white/60">
                            Maintenance, unavailable, and reserved are the only manual room states.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-lg p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                    <div class="space-y-4">
                        <div class="rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700">
                            <div class="flex gap-2">
                                <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0" />
                                <p>
                                    <template v-if="selectedOverride && overrideState(selectedOverride) === 'upcoming'">
                                        This override has not started yet. Clearing it will deactivate the scheduled override without deleting its history.
                                    </template>
                                    <template v-else>
                                        This override is currently active. Clearing it will end the override now and preserve the completed time window in history.
                                    </template>
                                </p>
                            </div>
                        </div>

                        <div v-if="selectedOverride" class="rounded-xl border border-gray-100 p-4">
                            <p class="font-mono text-xs font-semibold text-pup-maroon">{{ selectedOverride.room_code }}</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ selectedOverride.room_name }}</p>
                            <span :class="['mt-3 inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold', statusBadgeClass(selectedOverride.status)]">
                                {{ statusLabel(selectedOverride.status) }}
                            </span>
                            <p class="mt-3 text-sm text-gray-600">{{ selectedOverride.reason || 'No reason provided.' }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button
                        type="button"
                        @click="emit('cancel')"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        :disabled="!selectedOverride"
                        @click="emit('confirm')"
                        class="rounded-lg bg-red-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Clear Override
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
