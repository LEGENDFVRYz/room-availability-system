<script setup lang="ts">
import RoomMultiSelect from '@/components/RoomMultiSelect.vue';
import { RotateCcw, Search } from 'lucide-vue-next';
import type { RoomOption, RoomUsageFiltersState } from './type';

const props = defineProps<{
    filters: RoomUsageFiltersState;
    rooms: RoomOption[];
    resultCount: number;
}>();

const emit = defineEmits<{
    'update:filters': [filters: RoomUsageFiltersState];
    reset: [];
}>();

function setFilter<K extends keyof RoomUsageFiltersState>(key: K, value: RoomUsageFiltersState[K]) {
    emit('update:filters', {
        ...props.filters,
        [key]: value,
    });
}

function updateSearch(event: Event) {
    setFilter('search', (event.target as HTMLInputElement).value);
}

function updateDateFrom(event: Event) {
    setFilter('date_from', (event.target as HTMLInputElement).value);
}

function updateDateTo(event: Event) {
    setFilter('date_to', (event.target as HTMLInputElement).value);
}

function updateSource(event: Event) {
    setFilter('source', (event.target as HTMLSelectElement).value as RoomUsageFiltersState['source']);
}
</script>

<template>
    <div class="relative z-[70] rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-bold text-gray-900">Borrowing record filters</h2>
                <p class="text-xs text-gray-500">Showing {{ resultCount }} valid classroom borrowing record{{ resultCount === 1 ? '' : 's' }}.</p>
            </div>

            <button
                type="button"
                @click="emit('reset')"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold text-gray-600 transition hover:border-pup-maroon hover:text-pup-maroon"
            >
                <RotateCcw class="h-3.5 w-3.5" />
                Reset filters
            </button>
        </div>

        <div class="grid gap-3 lg:grid-cols-[1.4fr_0.85fr_0.85fr_1fr_0.9fr]">
            <label class="space-y-1.5">
                <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Search</span>
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        :value="filters.search"
                        type="search"
                        placeholder="Room, class, section, instructor..."
                        class="h-10 w-full rounded-lg border border-gray-200 bg-white pl-9 pr-3 text-sm text-gray-700 outline-none transition placeholder:text-gray-400 focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/10"
                        @input="updateSearch"
                    />
                </div>
            </label>

            <label class="space-y-1.5">
                <span class="text-xs font-bold uppercase tracking-wide text-gray-500">From</span>
                <input
                    :value="filters.date_from"
                    type="date"
                    class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 outline-none transition focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/10"
                    @input="updateDateFrom"
                />
            </label>

            <label class="space-y-1.5">
                <span class="text-xs font-bold uppercase tracking-wide text-gray-500">To</span>
                <input
                    :value="filters.date_to"
                    type="date"
                    class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 outline-none transition focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/10"
                    @input="updateDateTo"
                />
            </label>

            <RoomMultiSelect
                :model-value="filters.room_ids"
                :rooms="rooms"
                label="Room"
                all-label="All rooms"
                select-label="Select rooms"
                size="compact"
                @update:model-value="setFilter('room_ids', $event)"
            />

            <label class="space-y-1.5">
                <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Borrowing type</span>
                <select
                    :value="filters.source"
                    class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 outline-none transition focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/10"
                    @change="updateSource"
                >
                    <option value="all">All valid types</option>
                    <option value="schedule">Regular schedule</option>
                    <option value="schedule_exception">Daily operation</option>
                </select>
            </label>
        </div>
    </div>
</template>
