<script setup lang="ts">
import { computed, ref } from 'vue';

// Types
export interface RoomEntry {
    id: number;
    subject: string;
    subject_code: string;
    section: string;
    day: number;
    start_time: string;
    end_time: string;
    instructor: string | null;
    room_id: number;
    color: string;
}

export interface RoomSchedule {
    id: number;
    code: string;
    name: string;
    type: string;
    schedules: RoomEntry[];
}


// Props n emits
const props = defineProps<{ roomSchedules: RoomSchedule[] }>();
const emit = defineEmits<{ openEdit: [entry: RoomEntry] }>();


// --- Day Selector
const DAYS_LONG  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const DAYS_SHORT = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const today    = new Date().getDay();
const todayIdx = today === 0 ? 6 : today - 1; // 0=Mon … 6=Sun
const selectedDay = ref<number>(todayIdx);

// --- Calendar constant
const START_HOUR  = 7;
const END_HOUR    = 21;
const HOUR_H      = 64;
const GRID_HEIGHT = (END_HOUR - START_HOUR) * HOUR_H;

const hours     = Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, i) => START_HOUR + i);
const hourLines = hours.slice(0, -1);


// --- Filtered Data
const roomsForDay = computed(() =>
    props.roomSchedules.map(room => ({
        ...room,
        schedules: room.schedules.filter(s => s.day === selectedDay.value),
    })),
);

const hasAnySchedule = computed(() =>
    roomsForDay.value.some(r => r.schedules.length > 0),
);


// --- Helpers
const parseMins = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
const topPx     = (t: string)            => ((parseMins(t) - START_HOUR * 60) / 60) * HOUR_H;
const heightPx  = (s: string, e: string) => ((parseMins(e) - parseMins(s)) / 60) * HOUR_H;

const fmtHour = (h: number) => `${h % 12 || 12}${h < 12 ? 'AM' : 'PM'}`;
const fmtTime = (t: string) => {
    const [h, m] = t.split(':').map(Number);
    return `${h % 12 || 12}:${m.toString().padStart(2, '0')}${h < 12 ? 'AM' : 'PM'}`;
};

const COLOR_CLASSES: Record<string, string> = {
    blue:   'bg-sky-100    border-sky-300    text-sky-900',
    rose:   'bg-rose-100   border-rose-300   text-rose-900',
    green:  'bg-green-100  border-green-300  text-green-900',
    orange: 'bg-orange-100 border-orange-300 text-orange-900',
    teal:   'bg-teal-100   border-teal-300   text-teal-900',
    violet: 'bg-violet-100 border-violet-300 text-violet-900',
    yellow: 'bg-yellow-100 border-yellow-300 text-yellow-900',
    purple: 'bg-purple-100 border-purple-300 text-purple-900',
};
const colorClass = (c: string) =>
    COLOR_CLASSES[c] ?? 'bg-gray-100 border-gray-300 text-gray-900';

const ROOM_TYPE_BADGE: Record<string, string> = {
    classroom:    'bg-blue-50 text-blue-600',
    laboratory:   'bg-violet-50 text-violet-600',
    office:       'bg-orange-50 text-orange-600',
    special_room: 'bg-teal-50 text-teal-600',
    other:        'bg-gray-100 text-gray-500',
};
</script>

<template>
    <div class="flex flex-col gap-4">

        <!-- Day tabs -->
        <div class="flex items-center gap-1 overflow-x-auto rounded-xl border border-gray-200 bg-white p-1.5 shadow-sm">
            <button
                v-for="(day, idx) in DAYS_LONG"
                :key="idx"
                @click="selectedDay = idx"
                class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150 whitespace-nowrap"
                :class="selectedDay === idx
                    ? 'bg-pup-maroon text-white shadow-sm'
                    : idx >= 5
                        ? 'text-gray-400 hover:bg-gray-100 hover:text-gray-600'
                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-800'"
            >
                <span class="hidden sm:inline">{{ day }}</span>
                <span class="sm:hidden">{{ DAYS_SHORT[idx] }}</span>
            </button>
        </div>

        <!-- Room grid -->
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="min-w-[900px]">

                <!-- Room header row -->
                <div class="flex border-b border-gray-200">
                    <div class="w-14 shrink-0 border-r border-gray-200 bg-gray-50/80" />
                    <div
                        v-for="room in roomsForDay"
                        :key="room.id"
                        class="flex min-w-[110px] flex-1 flex-col items-center justify-center border-r border-gray-100 px-1 py-2.5 last:border-r-0"
                        :class="room.type === 'office' ? 'bg-gray-50/60' : ''"
                    >
                        <span
                            class="mb-0.5 rounded px-1.5 py-0.5 font-mono text-[10px] font-semibold"
                            :class="ROOM_TYPE_BADGE[room.type] ?? ROOM_TYPE_BADGE.other"
                        >
                            {{ room.code }}
                        </span>
                        <span class="text-center text-[11px] font-semibold leading-tight text-gray-700">
                            {{ room.name }}
                        </span>
                    </div>
                </div>

                <!-- Grid body -->
                <div class="flex" :style="{ height: GRID_HEIGHT + 'px' }">

                    <!-- Time label column -->
                    <div class="relative w-14 shrink-0 border-r border-gray-200 bg-gray-50/80">
                        <div
                            v-for="h in hours"
                            :key="h"
                            class="absolute right-0 flex w-full items-center justify-end pr-2"
                            :style="{ top: ((h - START_HOUR) * HOUR_H - 8) + 'px' }"
                        >
                            <span class="text-[10px] font-medium leading-none text-gray-400">
                                {{ fmtHour(h) }}
                            </span>
                        </div>
                    </div>

                    <!-- Room columns -->
                    <div
                        v-for="room in roomsForDay"
                        :key="room.id"
                        class="relative min-w-[110px] flex-1 border-r border-gray-100 last:border-r-0"
                        :class="room.type === 'office' ? 'bg-gray-50/30' : ''"
                    >
                        <div
                            v-for="h in hourLines"
                            :key="'hr-' + h"
                            class="pointer-events-none absolute inset-x-0 border-t border-gray-100"
                            :style="{ top: ((h - START_HOUR) * HOUR_H) + 'px' }"
                        />
                        <div
                            v-for="h in hourLines"
                            :key="'hf-' + h"
                            class="pointer-events-none absolute inset-x-0 border-t border-dashed border-gray-50"
                            :style="{ top: ((h - START_HOUR) * HOUR_H + HOUR_H / 2) + 'px' }"
                        />

                        <div
                            v-for="entry in room.schedules"
                            :key="entry.id"
                            class="group absolute inset-x-1 cursor-pointer overflow-hidden rounded-md border px-1.5 py-1 transition-all hover:brightness-95 hover:shadow-md"
                            :class="colorClass(entry.color)"
                            :style="{
                                top:    topPx(entry.start_time) + 2 + 'px',
                                height: (heightPx(entry.start_time, entry.end_time) - 4) + 'px',
                            }"
                            @click="emit('openEdit', entry)"
                        >
                            <p class="text-[11px] font-bold leading-snug">{{ entry.section }}</p>
                            <p class="line-clamp-1 text-[10px] font-medium opacity-75 leading-snug">{{ entry.subject }}</p>
                            <p class="mt-0.5 text-[10px] opacity-55">{{ fmtTime(entry.start_time) }}–{{ fmtTime(entry.end_time) }}</p>
                            <p v-if="entry.instructor" class="mt-0.5 truncate text-[10px] opacity-45">{{ entry.instructor }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="!hasAnySchedule"
            class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-gray-300 bg-gray-50 py-10 text-center"
        >
            <p class="text-sm font-medium text-gray-500">
                No classes scheduled on {{ DAYS_LONG[selectedDay] }}
            </p>
            <p class="text-xs text-gray-400">All rooms are available for this day.</p>
        </div>

    </div>
</template>
