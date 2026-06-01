<script setup lang="ts">
import { CalendarDays, ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// ─── Types ────────────────────────────────────────────────────────────────────

export interface ScheduleEntry {
    id: number;
    subject: string;
    subject_code: string;
    section: string;
    day: number;
    start_time: string;
    end_time: string;
    room: string | null;
    room_id: number | null;
    instructor: string | null;
    color: string; // Kept for interface compatibility, but ignored in UI
}

interface Section {
    label: string;
    year: number;
    schedules: ScheduleEntry[];
}

// ─── Props / emits ────────────────────────────────────────────────────────────

const props = defineProps<{
    sections: Section[];
}>();

const emit = defineEmits<{
    openEdit:       [entry: ScheduleEntry];
    sectionChanged: [label: string];
}>();

// ─── Year / section state ─────────────────────────────────────────────────────

const YEAR_LABELS: Record<number, string> = {
    1: '1st Year', 2: '2nd Year', 3: '3rd Year', 4: '4th Year',
};

const defaultYear = props.sections.length > 0
    ? Math.max(...props.sections.map(s => s.year))
    : 1;

const selectedYear      = ref<number>(defaultYear);
const selectedSectionId = ref<string>(
    props.sections.find(s => s.year === defaultYear)?.label ?? props.sections[0]?.label ?? '',
);

const availableYears = computed(() =>
    [...new Set(props.sections.map(s => s.year))].sort() as number[],
);

const yearSections = computed(() =>
    props.sections.filter(s => s.year === selectedYear.value),
);

const selectedSection = computed(() =>
    props.sections.find(s => s.label === selectedSectionId.value),
);

const hasSchedules = computed(() => (selectedSection.value?.schedules.length ?? 0) > 0);

const schedulesByDay = computed<Record<number, ScheduleEntry[]>>(() => {
    const map: Record<number, ScheduleEntry[]> = Object.fromEntries(
        [...Array(7).keys()].map(i => [i, [] as ScheduleEntry[]]),
    );
    selectedSection.value?.schedules.forEach(e => map[e.day].push(e));
    return map;
});

// Notify parent whenever the selected section changes so it can pass the
// right defaultSection to the modal.
watch(selectedSectionId, label => emit('sectionChanged', label), { immediate: true });

function onSelectYear(y: number) {
    selectedYear.value = y;
    const first = props.sections.find(s => s.year === y);
    if (first) selectedSectionId.value = first.label;
}

// ─── Calendar constants / helpers ─────────────────────────────────────────────

const DAYS_LONG  = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const DAYS_SHORT = ['Mon',    'Tue',     'Wed',       'Thu',      'Fri',    'Sat',      'Sun'];

const START_HOUR  = 7;
const END_HOUR    = 21;
const HOUR_H      = 64;
const GRID_HEIGHT = (END_HOUR - START_HOUR) * HOUR_H;

const hours     = Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, i) => START_HOUR + i);
const hourLines = hours.slice(0, -1);

const parseMins = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
const topPx     = (t: string)            => ((parseMins(t) - START_HOUR * 60) / 60) * HOUR_H;
const heightPx  = (s: string, e: string) => ((parseMins(e) - parseMins(s)) / 60) * HOUR_H;

const fmtHour = (h: number) => `${h % 12 || 12}${h < 12 ? 'AM' : 'PM'}`;
const fmtTime = (t: string) => {
    const [h, m] = t.split(':').map(Number);
    return `${h % 12 || 12}:${m.toString().padStart(2, '0')}${h < 12 ? 'AM' : 'PM'}`;
};
</script>

<template>
    <div class="flex flex-col gap-5">

        <!-- Row 2: section dropdown (left) + year tabs (right) -->
        <div class="flex items-center justify-between gap-3">
            <div class="relative w-44">
                <select
                    v-model="selectedSectionId"
                    class="h-9 w-full appearance-none rounded-lg border border-gray-200 bg-white pl-3 pr-8 text-sm font-medium text-gray-800 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                >
                    <option v-for="sec in yearSections" :key="sec.label" :value="sec.label">
                        {{ sec.label }}
                    </option>
                </select>
                <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
            </div>

            <div class="flex gap-1 rounded-lg bg-gray-100 p-1 w-fit">
                <button
                    v-for="y in availableYears"
                    :key="y"
                    @click="onSelectYear(y)"
                    class="rounded-md px-4 py-1.5 text-sm font-medium transition-all duration-150"
                    :class="selectedYear === y
                        ? 'bg-white text-pup-maroon shadow-sm font-semibold'
                        : 'text-gray-500 hover:text-gray-700'"
                >
                    {{ YEAR_LABELS[y] ?? `Year ${y}` }}
                </button>
            </div>
        </div>

        <!-- Calendar grid -->
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="min-w-[760px]">

                <!-- Day header -->
                <div class="flex border-b border-pup-maroon-deep/30 bg-pup-maroon text-white">
                    <div class="flex w-14 shrink-0 items-center justify-center border-r border-white/15 bg-pup-maroon-deep px-2 py-3">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-white">TIME</span>
                    </div>

                    <div
                        v-for="(dayName, dayIdx) in DAYS_SHORT"
                        :key="dayIdx"
                        class="flex flex-1 items-center justify-center border-r border-white/15 bg-pup-maroon py-3 text-xs font-bold uppercase tracking-wide text-white last:border-r-0"
                    >
                        <span class="hidden lg:inline">{{ DAYS_LONG[dayIdx] }}</span>
                        <span class="lg:hidden">{{ dayName }}</span>
                    </div>
                </div>

                <!-- Grid body -->
                <div class="flex" :style="{ height: GRID_HEIGHT + 'px' }">

                    <!-- Time labels -->
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

                    <!-- Day columns -->
                    <div
                        v-for="(_, dayIdx) in DAYS_SHORT"
                        :key="dayIdx"
                        class="relative flex-1 border-r border-gray-100 last:border-r-0"
                        :class="dayIdx >= 5 ? 'bg-gray-50/30' : ''"
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
                            v-for="entry in schedulesByDay[dayIdx]"
                            :key="entry.id"
                            class="group absolute inset-x-1 cursor-pointer overflow-hidden rounded-md border px-1.5 py-1 transition-all hover:brightness-95 hover:shadow-md bg-pup-maroon-pale border-pup-maroon/20 text-pup-maroon-dark"
                            :style="{
                                top:    topPx(entry.start_time) + 2 + 'px',
                                height: (heightPx(entry.start_time, entry.end_time) - 4) + 'px',
                            }"
                            @click="emit('openEdit', entry)"
                        >
                            <p class="line-clamp-2 text-[11px] font-semibold leading-snug">{{ entry.subject }}</p>
                            <p class="mt-0.5 text-[10px] opacity-60 font-medium">{{ fmtTime(entry.start_time) }}–{{ fmtTime(entry.end_time) }}</p>
                            <p v-if="entry.room" class="mt-0.5 truncate text-[10px] opacity-50">{{ entry.room }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="!hasSchedules"
            class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-gray-300 bg-gray-50 py-12 text-center"
        >
            <CalendarDays class="h-8 w-8 text-gray-300" />
            <p class="text-sm font-medium text-gray-500">
                No schedules for {{ selectedSection?.label ?? 'this section' }} yet
            </p>
            <p class="text-xs text-gray-400">
                Click <strong>Add Schedule</strong> to create the first entry.
            </p>
        </div>

    </div>
</template>