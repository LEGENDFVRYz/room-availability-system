export interface CurrentTerm {
    school_year: string;
    semester_label: string;
    year_start: number;
    semester: number;
}

export interface SharedProps {
    currentTerm?: CurrentTerm | null;
}

export interface Room {
    id: number;
    code: string;
    name: string;
    type?: string;
}

export type DailySlotSource = 'schedule' | 'exception' | 'override';

export type DailySlotType =
    | 'regular'
    | 'cancellation'
    | 'room_change'
    | 'special_class'
    | 'makeup_class'
    | 'maintenance'
    | 'unavailable'
    | 'reserved';

export type DailySlotStatus =
    | 'scheduled'
    | 'pending'
    | 'ongoing'
    | 'completed'
    | 'cancelled'
    | 'auto_cancelled'
    | 'maintenance'
    | 'unavailable'
    | 'reserved';

export interface DailySlot {
    id: number | string;
    schedule_id?: number | null;
    exception_id?: number | null;
    override_id?: number | null;
    room_id: number;
    original_room_id?: number | null;
    original_room_code?: string | null;
    event_date: string;
    source: DailySlotSource;
    event_type: DailySlotType;
    status: DailySlotStatus;
    subject_code: string;
    subject_title: string;
    section: string;
    instructor_name?: string | null;
    start_time: string;
    end_time: string;
    reason?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
}

export interface RoomWithSlots extends Room {
    slots: DailySlot[];
}

export interface SummaryStats {
    freeRoomsNowCount: number;
    occupiedNowCount: number;
    upcomingSoonCount: number;
    exceptionCount: number;
    cancelledCount: number;
}

export interface TimeGroup {
    key: string;
    label: string;
    slots: DailySlot[];
}

export type ViewMode = 'room' | 'table';

export type SlotAction = 'cancel' | 'change-room' | 'start' | 'complete';

export interface ClassRequestPayload {
    event_type: 'special_class' | 'makeup_class';
    room_id: number | null;
    subject_code: string;
    subject_title: string;
    section: string;
    instructor_name: string;
    start_time: string;
    end_time: string;
    reason: string;
}

export interface SlotActionPayload {
    slot: DailySlot;
    action: SlotAction;
    reason: string;
    room_id: number | null;
}

export type YearLevel = '1' | '2' | '3' | '4' | 'unknown';
