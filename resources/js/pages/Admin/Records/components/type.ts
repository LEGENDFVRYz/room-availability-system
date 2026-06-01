export interface RoomOption {
    id: number;
    code: string;
    name?: string | null;
    room_type?: string | null;
}

export type RoomUsageSource = 'schedule' | 'schedule_exception';

export type RoomUsageStatus = 'occupied' | 'completed';

export type BorrowType = 'regular' | 'room_change' | 'special_class' | 'makeup_class' | 'daily_operation' | string;

export interface RoomUsageLogItem {
    id: number | string;
    usage_date: string;
    source: RoomUsageSource | string;
    schedule_id?: number | null;
    schedule_exception_id?: number | null;
    room_id: number;
    room_code?: string | null;
    room_name?: string | null;
    borrow_type?: BorrowType | null;
    subject_code: string;
    subject_title: string;
    section: string;
    instructor_name?: string | null;
    status: RoomUsageStatus | string;
    expected_start: string;
    expected_end: string;
    actual_start?: string | null;
    actual_end?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface RoomUsageFiltersState {
    search: string;
    date_from: string;
    date_to: string;
    room_ids: number[];
    borrow_type: 'all' | BorrowType;
}
