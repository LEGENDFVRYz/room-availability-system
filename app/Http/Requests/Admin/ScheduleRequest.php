<?php

namespace App\Http\Requests\Admin;

use App\Enums\DayOfWeek;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_code'    => ['required', 'string', 'max:20'],
            'subject_title'   => ['required', 'string', 'max:100'],
            'section'         => ['required', 'string', 'max:30'],
            'instructor_name' => ['nullable', 'string', 'max:100'],
            'room_id'         => ['required', 'integer', 'exists:tbl_rooms,id'],
            'day_of_week'     => ['required', 'integer', Rule::in(array_column(DayOfWeek::cases(), 'value'))],
            'start_time'      => ['required', 'date_format:H:i'],
            'end_time'        => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_code.required'  => 'Subject code is required.',
            'subject_code.max'       => 'Subject code must not exceed 20 characters.',
            'subject_title.required' => 'Subject title is required.',
            'subject_title.max'      => 'Subject title must not exceed 100 characters.',
            'section.required'       => 'Section is required.',
            'section.max'            => 'Section must not exceed 30 characters.',
            'room_id.required'       => 'Please select a room.',
            'room_id.exists'         => 'The selected room does not exist.',
            'day_of_week.required'   => 'Please select a day.',
            'day_of_week.in'         => 'Invalid day selected.',
            'start_time.required'    => 'Start time is required.',
            'start_time.date_format' => 'Start time must be a valid time (HH:MM).',
            'end_time.required'      => 'End time is required.',
            'end_time.date_format'   => 'End time must be a valid time (HH:MM).',
            'end_time.after'         => 'End time must be after start time.',
        ];
    }
}
