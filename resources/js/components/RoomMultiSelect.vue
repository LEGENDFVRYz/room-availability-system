<script setup lang="ts">
import { CheckCircle2, ChevronDown } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export interface RoomMultiSelectOption {
    id: number;
    code: string;
    name?: string | null;
    room_type?: string | null;
}

const props = withDefaults(
    defineProps<{
        rooms: RoomMultiSelectOption[];
        modelValue: number[];
        label?: string;
        allLabel?: string;
        selectLabel?: string;
        selectedLabel?: string;
        size?: 'default' | 'compact';
        showRoomName?: boolean;
    }>(),
    {
        label: 'Room',
        allLabel: 'All rooms',
        selectLabel: 'Select rooms',
        selectedLabel: 'rooms selected',
        size: 'default',
        showRoomName: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const root = ref<HTMLElement | null>(null);
const isOpen = ref(false);

const buttonSizeClass = computed(() => (props.size === 'compact' ? 'h-10 rounded-lg' : 'h-11 rounded-xl'));

const selectedRoomLabel = computed(() => {
    if (props.modelValue.length === 0) return props.allLabel;

    if (props.modelValue.length === 1) {
        return props.rooms.find((room) => room.id === props.modelValue[0])?.code ?? '1 room selected';
    }

    return `${props.modelValue.length} ${props.selectedLabel}`;
});

function isRoomSelected(roomId: number) {
    return props.modelValue.includes(roomId);
}

function toggleRoomSelection(roomId: number) {
    if (isRoomSelected(roomId)) {
        emit(
            'update:modelValue',
            props.modelValue.filter((id) => id !== roomId),
        );
        return;
    }

    emit('update:modelValue', [...props.modelValue, roomId]);
}

function clearRoomSelection() {
    emit('update:modelValue', []);
}

function closeWhenClickedOutside(event: MouseEvent) {
    if (!root.value || root.value.contains(event.target as Node)) return;
    isOpen.value = false;
}

onMounted(() => document.addEventListener('click', closeWhenClickedOutside));
onBeforeUnmount(() => document.removeEventListener('click', closeWhenClickedOutside));
</script>

<template>
    <div ref="root" class="relative flex w-full min-w-0 flex-col gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
        <span>{{ label }}</span>

        <button
            type="button"
            @click.stop="isOpen = !isOpen"
            class="flex w-full min-w-0 items-center justify-between border border-gray-200 bg-white px-3 text-left text-sm font-medium normal-case tracking-normal text-gray-700 shadow-sm transition hover:border-pup-maroon/40 focus:border-pup-maroon focus:outline-none focus:ring-2 focus:ring-pup-maroon/15"
            :class="buttonSizeClass"
        >
            <span class="truncate">{{ selectedRoomLabel }}</span>
            <ChevronDown class="ml-2 h-4 w-4 shrink-0 text-gray-400 transition" :class="isOpen ? 'rotate-180' : ''" />
        </button>

        <div
            v-if="isOpen"
            class="absolute left-0 right-0 top-full z-[90] mt-2 w-full overflow-hidden rounded-xl border border-pup-maroon/15 bg-white text-left shadow-2xl ring-1 ring-black/5"
            @click.stop
        >
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-3 py-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ selectLabel }}</span>
                <button
                    type="button"
                    @click="clearRoomSelection"
                    class="text-xs font-semibold normal-case tracking-normal text-pup-maroon hover:underline"
                >
                    {{ allLabel }}
                </button>
            </div>

            <div class="max-h-64 overflow-y-auto p-1.5 [scrollbar-color:theme(colors.pup.gray-400)_transparent]">
                <button
                    v-for="room in rooms"
                    :key="room.id"
                    type="button"
                    @click="toggleRoomSelection(room.id)"
                    class="flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left text-sm normal-case tracking-normal transition hover:bg-pup-maroon-pale/70"
                    :class="isRoomSelected(room.id) ? 'bg-pup-maroon-pale text-pup-maroon' : 'text-gray-700'"
                >
                    <span class="min-w-0">
                        <span class="block truncate font-mono font-semibold">{{ room.code }}</span>
                        <span v-if="showRoomName && room.name" class="block truncate text-xs font-medium text-gray-400">{{ room.name }}</span>
                    </span>

                    <span
                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded border"
                        :class="isRoomSelected(room.id) ? 'border-pup-maroon bg-pup-maroon text-white' : 'border-gray-300 bg-white'"
                    >
                        <CheckCircle2 v-if="isRoomSelected(room.id)" class="h-3 w-3" />
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
