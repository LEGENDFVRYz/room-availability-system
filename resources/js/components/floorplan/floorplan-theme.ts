import type { FloorplanTheme } from './floorplan-types';

/**
 * Edit colors here.
 * These colors are intentionally soft so they work well on an off-white dashboard background.
 */
export const defaultFloorplanTheme: FloorplanTheme = {
    mapBackground: '#fbf8f8',
    panelBackground: '#fffdf8',
    panelBorder: '#ded8cc',

    validRoomFill: '#dff2df',
    validRoomStroke: '#2f3b32',
    officeFill: '#dfe6ff',
    officeStroke: '#344062',

    corridorFill: '#d8d5cf',
    corridorStroke: '#d8d5cf',  // easier way to make it cleaner
    toiletFill: '#eeeae2',      
    toiletStroke: '#4f4b45',
    serviceFill: '#e6e1d8',
    serviceStroke: '#4a463f',
    stageFill: '#f3f4f6',      
    stageStroke: '#6b7280',
    stageTextColor: '#4b5563', 
    ignoredFill: '#2c2b28',
    ignoredStroke: '#1f1e1c',

    courtFill: '#f8f4ed',
    courtStroke: '#7c756b',
    courtDashStroke: '#cfc8bc',

    stairFill: '#ebe7df',
    stairStroke: '#2d2b27',
    stairLine: '#35322d',

    labelColor: '#283238',
    mutedLabelColor: '#8e877c',
    selectedStroke: '#2563eb',
    hoverStroke: '#0f172a',

    statusAvailableFill: '#dff2df',
    statusAvailableStroke: '#2f7d46',
    statusOccupiedFill: '#ffe1dd',
    statusOccupiedStroke: '#c24133',
    statusReservedFill: '#fff3c4',
    statusReservedStroke: '#a26a00',
    statusMaintenanceFill: '#999da0',
    statusMaintenanceStroke: '#5b554d',
    statusInactiveFill: '#e5e7eb',
    statusInactiveStroke: '#9ca3af',
    statusInactiveLabelColor: '#6b7280',
    statusUnknownFill: '#f5f1e8',
    statusUnknownStroke: '#7d766d',
};
