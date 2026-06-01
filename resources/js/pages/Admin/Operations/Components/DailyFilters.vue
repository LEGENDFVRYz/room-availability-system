<script setup lang="ts">
import AdminFilterField from '@/components/AdminFilterField.vue';
import AdminFilterPanel from '@/components/AdminFilterPanel.vue';
import RoomMultiSelect from '@/components/RoomMultiSelect.vue';
import { LayoutGrid, Table2 } from 'lucide-vue-next';
import type { DailySlotType, Room, ViewMode } from './type';

const props = defineProps<{
    rooms: Room[];
    selectedDate: string;
    selectedRoomIds: number[];
    selectedType: 'all' | DailySlotType;
    viewMode: ViewMode;
}>();

const emit = defineEmits<{
    'update:selectedDate': [value: string];
    'update:selectedRoomIds': [value: number[]];
    'update:selectedType': [value: 'all' | DailySlotType];
    'update:viewMode': [value: ViewMode];
    'date-change': [];
}>();

const filterControlClass =
    'h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium normal-case tracking-normal text-gray-700 shadow-sm outline-none transition focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15';

function onDateInput(event: Event) {
    emit('update:selectedDate', (event.target as HTMLInputElement).value);
}
</script>

<template>
    <AdminFilterPanel>
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <AdminFilterField label="Date">
                    <input
                        :value="selectedDate"
                        type="date"
                        :class="filterControlClass"
                        @input="onDateInput"
                        @change="emit('date-change')"
                    />
                </AdminFilterField>

                <RoomMultiSelect
                    :model-value="selectedRoomIds"
                    :rooms="rooms"
                    label="Room"
                    all-label="All rooms"
                    select-label="Select rooms"
                    @update:model-value="emit('update:selectedRoomIds', $event)"
                />

                <AdminFilterField label="Type">
                    <select
                        :value="selectedType"
                        :class="filterControlClass"
                        @change="emit('update:selectedType', ($event.target as HTMLSelectElement).value as 'all' | DailySlotType)"
                    >
                        <option value="all">All types</option>
                        <option value="regular">Regular</option>
                        <option value="cancellation">Cancellation</option>
                        <option value="room_change">Room Change</option>
                        <option value="special_class">Special Class</option>
                        <option value="makeup_class">Makeup Class</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="unavailable">Unavailable</option>
                        <option value="reserved">Reserved</option>
                    </select>
                </AdminFilterField>
            </div>

            <div class="inline-flex rounded-xl border border-gray-200 bg-gray-50 p-1">
                <button
                    type="button"
                    @click="emit('update:viewMode', 'room')"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="viewMode === 'room' ? 'bg-pup-maroon text-white shadow-sm' : 'text-gray-600 hover:bg-white'"
                >
                    <LayoutGrid class="h-4 w-4" />
                    Room View
                </button>
                <button
                    type="button"
                    @click="emit('update:viewMode', 'table')"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="viewMode === 'table' ? 'bg-pup-maroon text-white shadow-sm' : 'text-gray-600 hover:bg-white'"
                >
                    <Table2 class="h-4 w-4" />
                    Table View
                </button>
            </div>
        </div>
    </AdminFilterPanel>
</template>
