<script setup lang="ts">
import AdminFilterField from '@/components/AdminFilterField.vue';
import AdminFilterPanel from '@/components/AdminFilterPanel.vue';
import PdfExportButton from '@/components/PdfExportButton.vue';
import RoomMultiSelect from '@/components/RoomMultiSelect.vue';
import { RotateCcw, Search } from 'lucide-vue-next';
import type { BorrowType, RoomOption, RoomUsageFiltersState } from './type';

const props = defineProps<{
    filters: RoomUsageFiltersState;
    rooms: RoomOption[];
    resultCount: number;
    exportHref?: string;
}>();

const emit = defineEmits<{
    'update:filters': [filters: RoomUsageFiltersState];
    reset: [];
}>();

const filterControlClass =
    'h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium normal-case tracking-normal text-gray-700 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15';

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

function updateBorrowType(event: Event) {
    setFilter('borrow_type', (event.target as HTMLSelectElement).value as 'all' | BorrowType);
}
</script>

<template>
    <AdminFilterPanel
        title="Borrowing record filters"
        :subtitle="`Showing ${resultCount} valid classroom borrowing record${resultCount === 1 ? '' : 's'}.`"
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

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1.4fr_0.85fr_0.85fr_1fr_0.9fr]">
            <AdminFilterField label="Search">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        :value="filters.search"
                        type="search"
                        placeholder="Room, class, section, instructor..."
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

            <AdminFilterField label="Borrowing type">
                <select
                    :value="filters.borrow_type"
                    :class="`${filterControlClass} w-full`"
                    @change="updateBorrowType"
                >
                    <option value="all">All valid types</option>
                    <option value="regular">Regular class</option>
                    <option value="room_change">Room change</option>
                    <option value="special_class">Special class</option>
                    <option value="makeup_class">Makeup class</option>
                </select>
            </AdminFilterField>
        </div>
    </AdminFilterPanel>
</template>
