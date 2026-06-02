<script setup lang="ts">
import AdminFilterField from '@/components/AdminFilterField.vue';
import AdminFilterPanel from '@/components/AdminFilterPanel.vue';
import PdfExportButton from '@/components/PdfExportButton.vue';
import RoomMultiSelect from '@/components/RoomMultiSelect.vue';
import { RotateCcw, Search } from 'lucide-vue-next';
import type { ActivityCategory, ActivityLogFiltersState, RoomOption } from './type';

const props = defineProps<{
    filters: ActivityLogFiltersState;
    rooms: RoomOption[];
    resultCount: number;
    exportHref?: string;
}>();

const emit = defineEmits<{
    'update:filters': [filters: ActivityLogFiltersState];
    reset: [];
}>();

const filterControlClass =
    'h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium normal-case tracking-normal text-gray-700 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15';

function setFilter<K extends keyof ActivityLogFiltersState>(key: K, value: ActivityLogFiltersState[K]) {
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

function updateCategory(event: Event) {
    setFilter('category', (event.target as HTMLSelectElement).value as 'all' | ActivityCategory);
}
</script>

<template>
    <AdminFilterPanel
        title="Activity log filters"
        :subtitle="`Showing ${resultCount} admin activit${resultCount === 1 ? 'y' : 'ies'}.`"
    >
        <template #actions>
            <div class="flex flex-wrap items-center gap-2">
                <PdfExportButton
                    v-if="exportHref"
                    :href="exportHref"
                    :filters="filters"
                    label="Export PDF"
                />

                <button
                    type="button"
                    @click="emit('reset')"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 px-3 text-xs font-bold text-gray-600 transition hover:border-pup-maroon hover:text-pup-maroon"
                >
                    <RotateCcw class="h-3.5 w-3.5" />
                    Reset filters
                </button>
            </div>
        </template>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1.4fr_0.85fr_0.85fr_1fr_0.95fr]">
            <AdminFilterField label="Search">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        :value="filters.search"
                        type="search"
                        placeholder="Admin, room, action, details..."
                        :class="`${filterControlClass} w-full pl-9 pr-3`"
                        @input="updateSearch"
                    />
                </div>
            </AdminFilterField>

            <AdminFilterField label="From">
                <input
                    :value="filters.date_from"
                    type="date"
                    :class="`${filterControlClass} w-full`"
                    @input="updateDateFrom"
                />
            </AdminFilterField>

            <AdminFilterField label="To">
                <input
                    :value="filters.date_to"
                    type="date"
                    :class="`${filterControlClass} w-full`"
                    @input="updateDateTo"
                />
            </AdminFilterField>

            <RoomMultiSelect
                :model-value="filters.room_ids"
                :rooms="rooms"
                label="Room"
                all-label="All rooms"
                select-label="Select rooms"
                @update:model-value="setFilter('room_ids', $event)"
            />

            <AdminFilterField label="Activity type">
                <select
                    :value="filters.category"
                    :class="`${filterControlClass} w-full`"
                    @change="updateCategory"
                >
                    <option value="all">All activities</option>
                    <option value="cancellation">Cancelled classes</option>
                    <option value="room_change">Room changes</option>
                    <option value="class_request">Special / makeup classes</option>
                    <option value="room_override">Room overrides</option>
                    <option value="daily_exception">Daily exceptions</option>
                    <option value="revert_action">Revert actions</option>
                    <option value="system">System actions</option>
                </select>
            </AdminFilterField>
        </div>
    </AdminFilterPanel>
</template>
