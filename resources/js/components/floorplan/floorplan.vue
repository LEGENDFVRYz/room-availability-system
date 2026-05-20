<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    floorplanCanvas,
    floorplanCorridors,
    floorplanCourts,
    floorplanMainStair,
    floorplanRooms,
    floorplanServiceAreas,
    floorplanSideStairs,
    floorplanToilets,
} from './floorplan-layout';
import { defaultFloorplanTheme } from './floorplan-theme';
import type {
    FloorplanColorMode,
    FloorplanRoomLayout,
    FloorplanRoomStatus,
    FloorplanStair,
    FloorplanTheme,
} from './floorplan-types';

interface Props {
    initialRooms?: FloorplanRoomStatus[];
    apiUrl?: string;
    pollIntervalMs?: number;
    colorMode?: FloorplanColorMode;
    theme?: Partial<FloorplanTheme>;
    showPanel?: boolean;
    showLegend?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    initialRooms: () => [],
    apiUrl: '/api/dashboard/floor-status',
    pollIntervalMs: 30000,
    colorMode: 'category',
    theme: () => ({}),
    showPanel: true,
    showLegend: true,
});

const liveRooms = ref<FloorplanRoomStatus[]>(props.initialRooms);
const selectedRoomId = ref<string | null>(null);
const hoveredRoomId = ref<string | null>(null);
let poller: number | null = null;

const mapTheme = computed<FloorplanTheme>(() => ({
    ...defaultFloorplanTheme,
    ...props.theme,
}));

const roomStatusMap = computed<Record<string, FloorplanRoomStatus>>(() => {
    return Object.fromEntries(liveRooms.value.map((room) => [room.room_id, room]));
});

const selectedLayoutRoom = computed<FloorplanRoomLayout | null>(() => {
    return floorplanRooms.find((room) => room.id === selectedRoomId.value) ?? null;
});

const selectedRoomData = computed<FloorplanRoomStatus | null>(() => {
    if (!selectedRoomId.value) return null;

    return getRoomData(selectedRoomId.value);
});

const shellClass = computed(() => [
    'grid gap-4 items-start',
    props.showPanel ? 'lg:grid-cols-[minmax(0,1fr)_20rem]' : 'grid-cols-1',
]);

watch(
    () => props.initialRooms,
    (rooms) => {
        liveRooms.value = rooms;
    },
);

function getRoomTitle(layoutRoom: FloorplanRoomLayout | null | undefined): string {
    return layoutRoom?.label?.join(' ') ?? '';
}

function getRoomData(roomId: string): FloorplanRoomStatus {
    const layoutRoom = floorplanRooms.find((room) => room.id === roomId);

    return (
        roomStatusMap.value[roomId] ?? {
            room_id: roomId,
            label: layoutRoom ? getRoomTitle(layoutRoom) : roomId,
            status: 'unknown',
            subject: null,
            section: null,
            teacher: null,
        }
    );
}

function roomBaseFill(room: FloorplanRoomLayout): string {
    if (props.colorMode === 'status') {
        const status = getRoomData(room.id).status ?? 'unknown';
        const key = `status${toPascalCase(status)}Fill`;

        return mapTheme.value[key] ?? mapTheme.value.statusUnknownFill;
    }

    return room.category === 'office' ? mapTheme.value.officeFill : mapTheme.value.validRoomFill;
}

function roomBaseStroke(room: FloorplanRoomLayout): string {
    if (props.colorMode === 'status') {
        const status = getRoomData(room.id).status ?? 'unknown';
        const key = `status${toPascalCase(status)}Stroke`;

        return mapTheme.value[key] ?? mapTheme.value.statusUnknownStroke;
    }

    return room.category === 'office' ? mapTheme.value.officeStroke : mapTheme.value.validRoomStroke;
}

function roomStroke(room: FloorplanRoomLayout): string {
    if (selectedRoomId.value === room.id) return mapTheme.value.selectedStroke;
    if (hoveredRoomId.value === room.id) return mapTheme.value.hoverStroke;

    return roomBaseStroke(room);
}

function roomStrokeWidth(room: FloorplanRoomLayout): number {
    if (selectedRoomId.value === room.id) return 4;
    if (hoveredRoomId.value === room.id) return 3.2;

    return 2.2;
}

function roomOpacity(room: FloorplanRoomLayout): number {
    return hoveredRoomId.value === room.id ? 0.92 : 1;
}

function toPascalCase(value: unknown): string {
    return String(value)
        .split(/[_\s-]+/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
        .join('');
}

function shapeCenterX(shape: FloorplanRoomLayout): number {
    return shape.labelX ?? shape.x + shape.w / 2;
}

function shapeCenterY(shape: FloorplanRoomLayout): number {
    return shape.labelY ?? shape.y + shape.h / 2;
}

function labelStartY(shape: FloorplanRoomLayout): number {
    const gap = shape.fs + 3;

    return shapeCenterY(shape) - ((shape.label.length - 1) * gap) / 2 + shape.fs * 0.35;
}

function boxLabelY(box: { y: number; h: number; label: string[]; fs: number }, index: number): number {
    return box.y + box.h / 2 - ((box.label.length - 1) * (box.fs + 3)) / 2 + box.fs * 0.35 + index * (box.fs + 3);
}

function stairLines(stair: FloorplanStair): number[] {
    return Array.from({ length: stair.lines ?? 11 }, (_, index) => index);
}

function selectRoom(roomId: string): void {
    selectedRoomId.value = roomId;
}

function formatStatus(status: unknown): string {
    return String(status ?? 'unknown').replaceAll('_', ' ');
}

async function refreshStatuses(): Promise<void> {
    if (!props.apiUrl) return;

    try {
        const response = await fetch(props.apiUrl, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        liveRooms.value = (await response.json()) as FloorplanRoomStatus[];
    } catch (error) {
        console.error('Failed to fetch floor status:', error);
    }
}

onMounted(() => {
    if (props.pollIntervalMs > 0) {
        poller = window.setInterval(refreshStatuses, props.pollIntervalMs);
    }
});

onUnmounted(() => {
    if (poller) window.clearInterval(poller);
});
</script>

<template>
    <div :class="shellClass">
        <section
            class="overflow-x-auto"
            :style="{
                backgroundColor: mapTheme.panelBackground,
                borderColor: mapTheme.panelBorder,
            }"
        >
            <svg
                :viewBox="`${floorplanCanvas.x} ${floorplanCanvas.y} ${floorplanCanvas.w} ${floorplanCanvas.h}`"
                class="block h-auto w-full min-w-[1000px] select-none"
                role="img"
                aria-label="Simplified floorplan"
            >
                <rect
                    :x="floorplanCanvas.x"
                    :y="floorplanCanvas.y"
                    :width="floorplanCanvas.w"
                    :height="floorplanCanvas.h"
                    :fill="mapTheme.mapBackground"
                />

                <!-- Corridor paths: intentionally no text labels. -->
                <g aria-hidden="true">
                    <rect
                        v-for="corridor in floorplanCorridors"
                        :key="`corridor-${corridor.x}-${corridor.y}`"
                        :x="corridor.x"
                        :y="corridor.y"
                        :width="corridor.w"
                        :height="corridor.h"
                        :fill="mapTheme.corridorFill"
                        :stroke="mapTheme.corridorStroke"
                        stroke-width="1.5"
                        vector-effect="non-scaling-stroke"
                    />
                </g>

                <!-- Open courts: intentionally muted so they do not overpower the rooms. -->
                <g aria-hidden="true">
                    <g v-for="court in floorplanCourts" :key="`court-${court.x}-${court.y}`">
                        <rect
                            :x="court.x"
                            :y="court.y"
                            :width="court.w"
                            :height="court.h"
                            :fill="mapTheme.courtFill"
                            :stroke="mapTheme.courtStroke"
                            stroke-width="1.8"
                            vector-effect="non-scaling-stroke"
                        />
                        <rect
                            :x="court.x + 22"
                            :y="court.y + 22"
                            :width="court.w - 44"
                            :height="court.h - 44"
                            fill="none"
                            :stroke="mapTheme.courtDashStroke"
                            stroke-width="1.2"
                            stroke-dasharray="8 8"
                            vector-effect="non-scaling-stroke"
                        />
                        <line
                            :x1="court.x + 22"
                            :y1="court.y + 22"
                            :x2="court.x + court.w - 22"
                            :y2="court.y + court.h - 22"
                            :stroke="mapTheme.courtDashStroke"
                            stroke-width="1"
                            opacity="0.55"
                            vector-effect="non-scaling-stroke"
                        />
                        <line
                            :x1="court.x + court.w - 22"
                            :y1="court.y + 22"
                            :x2="court.x + 22"
                            :y2="court.y + court.h - 22"
                            :stroke="mapTheme.courtDashStroke"
                            stroke-width="1"
                            opacity="0.55"
                            vector-effect="non-scaling-stroke"
                        />
                        <text
                            v-for="(line, index) in court.label"
                            :key="`${line}-${index}`"
                            :x="court.x + court.w / 2"
                            :y="boxLabelY(court, index)"
                            text-anchor="middle"
                            class="pointer-events-none select-none font-sans font-bold tracking-widest"
                            :fill="mapTheme.mutedLabelColor"
                            :font-size="court.fs"
                        >
                            {{ line }}
                        </text>
                    </g>
                </g>

                <!-- Bathrooms and minor service rooms. -->
                <g aria-hidden="true">
                    <g v-for="toilet in floorplanToilets" :key="`toilet-${toilet.x}-${toilet.y}`">
                        <rect
                            :x="toilet.x"
                            :y="toilet.y"
                            :width="toilet.w"
                            :height="toilet.h"
                            :fill="mapTheme.toiletFill"
                            :stroke="mapTheme.toiletStroke"
                            stroke-width="1.8"
                            vector-effect="non-scaling-stroke"
                        />
                        <text
                            v-for="(line, index) in toilet.label"
                            :key="`${line}-${index}`"
                            :x="toilet.x + toilet.w / 2"
                            :y="boxLabelY(toilet, index)"
                            text-anchor="middle"
                            class="pointer-events-none select-none font-sans font-bold"
                            :fill="mapTheme.labelColor"
                            :font-size="toilet.fs"
                        >
                            {{ line }}
                        </text>
                    </g>

                    <g v-for="area in floorplanServiceAreas" :key="`service-${area.kind}-${area.x}-${area.y}`">
                        <!-- Stage -->
                        <g v-if="area.kind === 'stage'">
                            <!-- Main stage body -->
                            <rect
                                :x="area.x"
                                :y="area.y"
                                :width="area.w"
                                :height="area.h"
                                :fill="mapTheme.stageFill"
                                :stroke="mapTheme.stageStroke"
                                stroke-width="2"
                                vector-effect="non-scaling-stroke"
                            />

                            <!-- Stage label -->
                            <text
                                v-for="(line, index) in area.label"
                                :key="`${line}-${index}`"
                                :x="area.x + area.w / 2"
                                :y="boxLabelY(area, index)"
                                text-anchor="middle"
                                class="pointer-events-none select-none font-sans font-bold"
                                :fill="mapTheme.stageTextColor"
                                :font-size="area.fs"
                            >
                                {{ line }}
                            </text>
                        </g>

                        <!-- Dark ignored rooms / stock rooms / elevator -->
                        <g v-else-if="area.kind === 'ignored'">
                            <rect
                                :x="area.x"
                                :y="area.y"
                                :width="area.w"
                                :height="area.h"
                                :fill="mapTheme.ignoredFill"
                                :stroke="mapTheme.ignoredStroke"
                                stroke-width="1.8"
                                vector-effect="non-scaling-stroke"
                            />
                        </g>

                        <!-- Normal service areas -->
                        <g v-else>
                            <rect
                                :x="area.x"
                                :y="area.y"
                                :width="area.w"
                                :height="area.h"
                                :fill="mapTheme.serviceFill"
                                :stroke="mapTheme.serviceStroke"
                                stroke-width="1.8"
                                vector-effect="non-scaling-stroke"
                            />

                            <text
                                v-for="(line, index) in area.label"
                                :key="`${line}-${index}`"
                                :x="area.x + area.w / 2"
                                :y="boxLabelY(area, index)"
                                text-anchor="middle"
                                class="pointer-events-none select-none font-sans font-bold"
                                :fill="mapTheme.labelColor"
                                :font-size="area.fs"
                            >
                                {{ line }}
                            </text>
                        </g>
                    </g>
                </g>

                <!-- Emergency and side stairs. -->
                <g aria-hidden="true">
                    <g v-for="stair in floorplanSideStairs" :key="`stair-${stair.x}-${stair.y}`">
                        <rect
                            :x="stair.x"
                            :y="stair.y"
                            :width="stair.w"
                            :height="stair.h"
                            :fill="mapTheme.stairFill"
                            :stroke="mapTheme.stairStroke"
                            stroke-width="1.8"
                            vector-effect="non-scaling-stroke"
                        />

                        <template v-if="stair.direction === 'vertical'">
                            <line
                                v-for="index in stairLines(stair)"
                                :key="`vertical-${index}`"
                                :x1="stair.x + 4 + index * (stair.w - 8) / ((stair.lines ?? 11) - 1)"
                                :y1="stair.y + 4"
                                :x2="stair.x + 4 + index * (stair.w - 8) / ((stair.lines ?? 11) - 1)"
                                :y2="stair.y + stair.h - 4"
                                :stroke="mapTheme.stairLine"
                                stroke-width="1.2"
                                vector-effect="non-scaling-stroke"
                            />
                        </template>

                        <template v-else>
                            <line
                                v-for="index in stairLines(stair)"
                                :key="`horizontal-${index}`"
                                :x1="stair.x + 4"
                                :y1="stair.y + 4 + index * (stair.h - 8) / ((stair.lines ?? 11) - 1)"
                                :x2="stair.x + stair.w - 4"
                                :y2="stair.y + 4 + index * (stair.h - 8) / ((stair.lines ?? 11) - 1)"
                                :stroke="mapTheme.stairLine"
                                stroke-width="1.2"
                                vector-effect="non-scaling-stroke"
                            />
                        </template>
                    </g>

                    <!-- Main center stair with rounded circulation path. -->
                    <g>
                        <rect
                            :x="floorplanMainStair.x"
                            :y="floorplanMainStair.y"
                            :width="floorplanMainStair.w"
                            :height="floorplanMainStair.h"
                            :fill="mapTheme.stairFill"
                            :stroke="mapTheme.stairStroke"
                            stroke-width="1.8"
                            vector-effect="non-scaling-stroke"
                        />
                        <rect
                            :x="floorplanMainStair.x"
                            :y="floorplanMainStair.y + (floorplanMainStair.h / 2)"
                            :width="floorplanMainStair.w"
                            :height="floorplanMainStair.h / 2"
                            :fill="mapTheme.stairFill"
                            :stroke="mapTheme.stairStroke"
                            stroke-width="1.8"
                            vector-effect="non-scaling-stroke"
                        />
                        <line
                            v-for="index in Array.from({ length: floorplanMainStair.lines }, (_, itemIndex) => itemIndex)"
                            :key="`main-stair-line-${index}`"
                            :x1="floorplanMainStair.x + index * floorplanMainStair.w / (floorplanMainStair.lines - 1)"
                            :y1="floorplanMainStair.y + 5"
                            :x2="floorplanMainStair.x + index * floorplanMainStair.w / (floorplanMainStair.lines - 1)"
                            :y2="floorplanMainStair.y + floorplanMainStair.h - 5"
                            :stroke="mapTheme.stairLine"
                            stroke-width="1.2"
                            vector-effect="non-scaling-stroke"
                        />
                        <path
                            :d="`M ${floorplanMainStair.x + floorplanMainStair.w} ${floorplanMainStair.y} L ${floorplanMainStair.x + floorplanMainStair.w + floorplanMainStair.curveW} ${floorplanMainStair.y} A ${floorplanMainStair.curveR} ${floorplanMainStair.curveR} 0 0 1 ${floorplanMainStair.x + floorplanMainStair.w + floorplanMainStair.curveW} ${floorplanMainStair.y + floorplanMainStair.h} L ${floorplanMainStair.x + floorplanMainStair.w} ${floorplanMainStair.y + floorplanMainStair.h} Z`"
                            :fill="mapTheme.stairFill"
                            :stroke="mapTheme.stairStroke"
                            stroke-width="1.8"
                            vector-effect="non-scaling-stroke"
                        />
                    </g>
                </g>

                <!-- Dynamic room layer. -->
                <g>
                    <g
                        v-for="room in floorplanRooms"
                        :key="room.id"
                        class="cursor-pointer outline-none"
                        tabindex="0"
                        role="button"
                        :aria-label="`${getRoomData(room.id).label ?? getRoomTitle(room)} - ${formatStatus(getRoomData(room.id).status)}`"
                        @mouseenter="hoveredRoomId = room.id"
                        @mouseleave="hoveredRoomId = null"
                        @focus="hoveredRoomId = room.id"
                        @blur="hoveredRoomId = null"
                        @click="selectRoom(room.id)"
                        @keydown.enter.prevent="selectRoom(room.id)"
                        @keydown.space.prevent="selectRoom(room.id)"
                    >
                        <rect
                            v-if="room.shape === 'rect'"
                            :id="room.id"
                            :x="room.x"
                            :y="room.y"
                            :width="room.w"
                            :height="room.h"
                            rx="2"
                            class="transition-opacity duration-150"
                            :fill="roomBaseFill(room)"
                            :stroke="roomStroke(room)"
                            :stroke-width="roomStrokeWidth(room)"
                            :opacity="roomOpacity(room)"
                            vector-effect="non-scaling-stroke"
                        />

                        <polygon
                            v-else-if="room.shape === 'polygon'"
                            :id="room.id"
                            :points="room.points"
                            class="transition-opacity duration-150"
                            :fill="roomBaseFill(room)"
                            :stroke="roomStroke(room)"
                            :stroke-width="roomStrokeWidth(room)"
                            :opacity="roomOpacity(room)"
                            vector-effect="non-scaling-stroke"
                        />

                        <text
                            v-for="(line, index) in room.label"
                            :key="`${line}-${index}`"
                            :x="shapeCenterX(room)"
                            :y="labelStartY(room) + index * (room.fs + 3)"
                            text-anchor="middle"
                            class="pointer-events-none select-none font-sans font-bold"
                            :fill="mapTheme.labelColor"
                            :font-size="room.fs"
                        >
                            {{ line }}
                        </text>

                        <title>{{ getRoomData(room.id).label }} - {{ formatStatus(getRoomData(room.id).status) }}</title>
                    </g>
                </g>
            </svg>

            <div
                v-if="showLegend"
                class="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-center text-sm"
                :style="{ color: mapTheme.labelColor }"
            >
                <span class="inline-flex items-center gap-2">
                    <span
                        class="h-4 w-4 rounded border"
                        :style="{ backgroundColor: mapTheme.validRoomFill, borderColor: mapTheme.validRoomStroke }"
                    />
                    Valid rooms
                </span>
                <span class="inline-flex items-center gap-2">
                    <span
                        class="h-4 w-4 rounded border"
                        :style="{ backgroundColor: mapTheme.officeFill, borderColor: mapTheme.officeStroke }"
                    />
                    Fixed offices
                </span>
                <span class="inline-flex items-center gap-2">
                    <span
                        class="h-4 w-4 rounded border"
                        :style="{ backgroundColor: mapTheme.corridorFill, borderColor: mapTheme.corridorStroke }"
                    />
                    Corridors
                </span>
                <span class="inline-flex items-center gap-2">
                    <span
                        class="h-4 w-4 rounded border"
                        :style="{ backgroundColor: mapTheme.toiletFill, borderColor: mapTheme.toiletStroke }"
                    />
                    Bathrooms
                </span>
                <span class="inline-flex items-center gap-2">
                    <span
                        class="h-4 w-4 rounded border"
                        :style="{ backgroundColor: mapTheme.ignoredFill, borderColor: mapTheme.ignoredStroke }"
                    />
                    Stock / elevator
                </span>
            </div>
        </section>
        
    </div>
</template>
