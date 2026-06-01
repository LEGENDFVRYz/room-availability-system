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


type YearLevel = '1' | '2' | '3' | '4' | 'unknown';

// Props n emits
const props = defineProps<{ roomSchedules: RoomSchedule[] }>();
const emit = defineEmits<{ openEdit: [entry: RoomEntry] }>();


// --- Day Selector
const DAYS_LONG  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const DAYS_SHORT = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const today    = new Date().getDay();
const todayIdx = today === 0 ? 6 : today - 1; // 0=Mon … 6=Sun
const selectedDay = ref<number>(todayIdx);

// --- Calendar constants aligned with DailyRoomGrid.vue
const START_HOUR = 7;
const END_HOUR = 21;
const HOUR_H = 76;
const TIME_COLUMN_WIDTH = 64;
const ROOM_COLUMN_WIDTH = 118;
const GRID_HEIGHT = (END_HOUR - START_HOUR) * HOUR_H;

const hours = Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, i) => START_HOUR + i);
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

const gridMinWidth = computed(() => `${TIME_COLUMN_WIDTH + roomsForDay.value.length * ROOM_COLUMN_WIDTH}px`);


// --- Helpers
function parseMins(time: string): number {
    const [hour, minute] = time.split(':').map(Number);
    return hour * 60 + minute;
}

function topPx(time: string): number {
    return ((parseMins(time) - START_HOUR * 60) / 60) * HOUR_H;
}

function heightPx(start: string, end: string): number {
    return Math.max(((parseMins(end) - parseMins(start)) / 60) * HOUR_H, 34);
}

function fmtHour(hour: number): string {
    return `${hour % 12 || 12}${hour < 12 ? 'AM' : 'PM'}`;
}

function fmtTime(time: string): string {
    const [hour, minute] = time.split(':').map(Number);
    return `${hour % 12 || 12}:${minute.toString().padStart(2, '0')}${hour < 12 ? 'AM' : 'PM'}`;
}

function yearLevel(entry: RoomEntry): YearLevel {
    const section = entry.section ?? '';
    const bscpeMatch = section.match(/BSCPE\s*([1-4])/i);
    const fallbackMatch = section.match(/(?:^|\s)([1-4])(?:[-\s]|$)/);
    const value = bscpeMatch?.[1] ?? fallbackMatch?.[1];

    return ['1', '2', '3', '4'].includes(value ?? '') ? (value as YearLevel) : 'unknown';
}

const YEAR_LEVEL_CLASS: Record<YearLevel, string> = {
    '1': 'border-sky-300 bg-sky-50 text-sky-950',
    '2': 'border-emerald-300 bg-emerald-50 text-emerald-950',
    '3': 'border-violet-300 bg-violet-50 text-violet-950',
    '4': 'border-pup-maroon/30 bg-pup-maroon-pale text-pup-maroon-deep',
    unknown: 'border-gray-200 bg-gray-50 text-gray-800',
};

function scheduleBlockClass(entry: RoomEntry): string {
    return `${YEAR_LEVEL_CLASS[yearLevel(entry)]} z-30`;
}
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
        <div class="relative z-0 rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto overflow-y-hidden rounded-md">
                <div class="w-full" :style="{ minWidth: gridMinWidth }">

                    <!-- Room header row -->
                    <div class="flex border-b border-pup-maroon-deep bg-pup-maroon text-white">
                        <div
                            class="sticky left-0 z-30 flex w-16 shrink-0 items-center justify-center border-r border-white/15 bg-pup-maroon-deep px-2 py-3 text-[10px] font-bold uppercase tracking-wider text-pup-gold-light shadow-[8px_0_12px_-12px_rgba(0,0,0,0.6)]"
                        >
                            Time
                        </div>

                        <div
                            v-for="room in roomsForDay"
                            :key="room.id"
                            :title="room.name"
                            class="flex min-w-[118px] flex-1 items-center justify-center border-r border-white/10 px-1.5 py-3 text-center last:border-r-0"
                        >
                            <span class="font-mono text-xs font-bold uppercase tracking-wide text-white">
                                {{ room.code }}
                            </span>
                        </div>
                    </div>

                    <!-- Grid body -->
                    <div class="relative flex" :style="{ minHeight: GRID_HEIGHT + 'px' }">

                        <!-- Time label column -->
                        <div class="sticky left-0 z-[80] w-16 shrink-0 border-r border-gray-200 bg-gray-50/95 shadow-[8px_0_12px_-12px_rgba(0,0,0,0.35)]">
                            <div
                                v-for="hour in hours"
                                :key="hour"
                                class="absolute right-0 flex w-full items-center justify-end pr-2"
                                :style="{ top: ((hour - START_HOUR) * HOUR_H - 8) + 'px' }"
                            >
                                <span class="text-[10px] font-medium leading-none text-gray-400">
                                    {{ fmtHour(hour) }}
                                </span>
                            </div>
                        </div>

                        <!-- Room columns -->
                        <div
                            v-for="room in roomsForDay"
                            :key="room.id"
                            class="group/room relative min-w-[118px] flex-1 border-r border-gray-100 last:border-r-0"
                            :class="room.type === 'office' ? 'bg-gray-50/30' : ''"
                        >
                            <div
                                v-for="hour in hourLines"
                                :key="`line-${room.id}-${hour}`"
                                class="pointer-events-none absolute inset-x-0 border-t border-gray-100"
                                :style="{ top: ((hour - START_HOUR) * HOUR_H) + 'px' }"
                            />
                            <div
                                v-for="hour in hourLines"
                                :key="`half-${room.id}-${hour}`"
                                class="pointer-events-none absolute inset-x-0 border-t border-dashed border-gray-50"
                                :style="{
                                    top: ((hour - START_HOUR) * HOUR_H + HOUR_H / 2) + 'px',
                                }"
                            />

                            <button
                                v-for="entry in room.schedules"
                                :key="entry.id"
                                type="button"
                                class="absolute inset-x-1 overflow-hidden rounded-md border px-1.5 py-1 text-left shadow-sm transition duration-150 hover:z-[60] hover:brightness-95 hover:shadow-md"
                                :class="scheduleBlockClass(entry)"
                                :style="{
                                    top: topPx(entry.start_time) + 3 + 'px',
                                    height: heightPx(entry.start_time, entry.end_time) - 6 + 'px',
                                }"
                                @click="emit('openEdit', entry)"
                            >
                                <div class="flex w-full min-w-0 items-center justify-between gap-2">
                                    <p class="line-clamp-2 text-[11px] font-bold leading-snug text-gray-900">
                                        {{ entry.subject_code }}
                                    </p>
                                </div>

                                <p class="line-clamp-2 text-[10px] font-semibold leading-snug opacity-80">
                                    {{ entry.subject }}
                                </p>
                                <p class="mt-0.5 text-[10px] opacity-65">
                                    {{ fmtTime(entry.start_time) }}–{{ fmtTime(entry.end_time) }}
                                </p>
                                <p class="truncate text-[10px] opacity-55">
                                    {{ entry.section }}
                                </p>
                                <p v-if="entry.instructor" class="mt-0.5 truncate text-[10px] opacity-45">
                                    {{ entry.instructor }}
                                </p>
                            </button>
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
