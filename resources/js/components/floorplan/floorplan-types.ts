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

export interface FloorplanRoomScheduleItem {
    id?: string | number | null;
    schedule_id?: number | null;
    exception_id?: number | null;
    override_id?: number | null;
    room_id?: number | string | null;
    original_room_id?: number | null;
    original_room_code?: string | null;
    event_date?: string | null;
    source?: string | null;
    event_type?: string | null;
    status?: string | null;
    floorplan_status?: string | null;
    subject_code?: string | null;
    subject_title?: string | null;
    section?: string | null;
    year_level?: string | null;
    instructor_name?: string | null;
    start_time?: string | null;
    end_time?: string | null;
    time_range?: string | null;
    reason?: string | null;
    is_current?: boolean;
}

export interface FloorplanRoomStatus {
    room_id: string;
    code?: string;
    label?: string;
    status?: string;
    raw_status?: string | null;
    source?: string | null;
    event_type?: string | null;
    subject?: string | null;
    subject_code?: string | null;
    section?: string | null;
    year_level?: string | null;
    teacher?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    current_section?: string | null;
    current_year_level?: string | null;
    current_subject?: string | null;
    current_time_range?: string | null;
    items?: FloorplanRoomScheduleItem[];
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
