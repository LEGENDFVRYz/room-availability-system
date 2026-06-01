export type FloorplanColorMode = 'category' | 'status';

export type FloorplanRoomCategory = 'valid' | 'office';

export type FloorplanShape = 'rect' | 'polygon';

export interface FloorplanRoomLayout {
    /** Keep this stable. Laravel room_id must match this value. */
    id: string;
    category: FloorplanRoomCategory;
    label: string[];
    shape: FloorplanShape;
    x: number;
    y: number;
    w: number;
    h: number;
    fs: number;
    /** Required only when shape is polygon. Example: '20,350 150,350 150,455'. */
    points?: string;
    /** Optional manual label center overrides, useful for odd-shaped rooms. */
    labelX?: number;
    labelY?: number;
}

export interface FloorplanBox {
    x: number;
    y: number;
    w: number;
    h: number;
}

export interface FloorplanLabeledBox extends FloorplanBox {
    label: string[];
    fs: number;
}

export interface FloorplanServiceArea extends FloorplanLabeledBox {
    kind: 'service' | 'stage' | 'ignored';
}

export interface FloorplanStair extends FloorplanBox {
    kind: 'emergency' | 'side';
    /** horizontal = stair lines go across, vertical = stair lines go top-to-bottom. */
    direction?: 'horizontal' | 'vertical';
    lines?: number;
}

export interface FloorplanMainStair {
    x: number;
    y: number;
    w: number;
    h: number;
    curveW: number;
    curveR: number;
    lines: number;
}

export interface FloorplanRoomStatus {
    room_id: string;
    label?: string;
    status?: string;
    subject?: string | null;
    section?: string | null;
    teacher?: string | null;
    [key: string]: unknown;
}

export interface FloorplanTheme {
    [key: string]: string;

    mapBackground: string;
    panelBackground: string;
    panelBorder: string;

    validRoomFill: string;
    validRoomStroke: string;
    officeFill: string;
    officeStroke: string;

    corridorFill: string;
    corridorStroke: string;
    toiletFill: string;
    toiletStroke: string;
    serviceFill: string;
    serviceStroke: string;
    ignoredFill: string;
    ignoredStroke: string;

    courtFill: string;
    courtStroke: string;
    courtDashStroke: string;

    stairFill: string;
    stairStroke: string;
    stairLine: string;

    labelColor: string;
    mutedLabelColor: string;
    selectedStroke: string;
    hoverStroke: string;

    statusAvailableFill: string;
    statusAvailableStroke: string;
    statusOccupiedFill: string;
    statusOccupiedStroke: string;
    statusReservedFill: string;
    statusReservedStroke: string;
    statusMaintenanceFill: string;
    statusMaintenanceStroke: string;
    statusInactiveFill: string;
    statusInactiveStroke: string;
    statusInactiveLabelColor: string;
    statusUnknownFill: string;
    statusUnknownStroke: string;
}
