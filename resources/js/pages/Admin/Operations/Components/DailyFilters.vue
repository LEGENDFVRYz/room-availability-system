<script setup lang="ts">
import { CheckCircle2, ChevronDown, LayoutGrid, Table2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
interface Room {
    id: number;
    code: string;
    name: string;
    type?: string;
}

type DailySlotType =
    | 'regular'
    | 'cancellation'
    | 'room_change'
    | 'special_class'
    | 'makeup_class'
    | 'maintenance'
    | 'unavailable'
    | 'reserved';

type ViewMode = 'room' | 'table';

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

const isRoomFilterOpen = ref(false);

const selectedRoomLabel = computed(() => {
    if (props.selectedRoomIds.length === 0) return 'All rooms';

    if (props.selectedRoomIds.length === 1) {
        return props.rooms.find((room) => room.id === props.selectedRoomIds[0])?.code ?? '1 room selected';
    }

    return `${props.selectedRoomIds.length} rooms selected`;
});

function toggleRoomSelection(roomId: number) {
    if (props.selectedRoomIds.includes(roomId)) {
        emit('update:selectedRoomIds', props.selectedRoomIds.filter((id) => id !== roomId));
        return;
    }

    emit('update:selectedRoomIds', [...props.selectedRoomIds, roomId]);
}

function clearRoomSelection() {
    emit('update:selectedRoomIds', []);
}

function isRoomSelected(roomId: number) {
    return props.selectedRoomIds.includes(roomId);
}

function onDateInput(event: Event) {
    emit('update:selectedDate', (event.target as HTMLInputElement).value);
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" @click="isRoomFilterOpen = false">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label class="flex flex-col gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Date
                    <input
                        :value="selectedDate"
                        type="date"
                        @input="onDateInput"
                        @change="emit('date-change')"
                        class="h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-700 shadow-sm transition focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15"
                    />
                </label>

                <div class="relative flex flex-col gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Room
                    <button
                        type="button"
                        @click.stop="isRoomFilterOpen = !isRoomFilterOpen"
                        class="flex h-11 items-center justify-between rounded-xl border border-gray-200 bg-white px-3 text-left text-sm font-medium normal-case tracking-normal text-gray-700 shadow-sm transition hover:border-pup-maroon/40 focus:border-pup-maroon focus:outline-none focus:ring-2 focus:ring-pup-maroon/15"
                    >
                        <span>{{ selectedRoomLabel }}</span>
                        <ChevronDown class="h-4 w-4 text-gray-400" />
                    </button>

                    <div
                        v-if="isRoomFilterOpen"
                        class="absolute left-0 top-full z-30 mt-2 w-full min-w-[260px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl"
                        @click.stop
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Select rooms</span>
                            <button
                                type="button"
                                @click="clearRoomSelection"
                                class="text-xs font-semibold normal-case tracking-normal text-pup-maroon hover:underline"
                            >
                                All rooms
                            </button>
                        </div>
                        <div class="max-h-64 overflow-y-auto p-1.5">
                            <button
                                v-for="room in rooms"
                                :key="room.id"
                                type="button"
                                @click="toggleRoomSelection(room.id)"
                                class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-sm normal-case tracking-normal transition hover:bg-pup-maroon-pale/70"
                                :class="isRoomSelected(room.id) ? 'bg-pup-maroon-pale text-pup-maroon' : 'text-gray-700'"
                            >
                                <span class="font-mono font-semibold">{{ room.code }}</span>
                                <span
                                    class="flex h-4 w-4 items-center justify-center rounded border"
                                    :class="isRoomSelected(room.id) ? 'border-pup-maroon bg-pup-maroon text-white' : 'border-gray-300 bg-white'"
                                >
                                    <CheckCircle2 v-if="isRoomSelected(room.id)" class="h-3 w-3" />
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <label class="flex flex-col gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Type
                    <select
                        :value="selectedType"
                        @change="emit('update:selectedType', ($event.target as HTMLSelectElement).value as 'all' | DailySlotType)"
                        class="h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium normal-case tracking-normal text-gray-700 shadow-sm transition focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15"
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
                </label>
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
    </div>
</template>
