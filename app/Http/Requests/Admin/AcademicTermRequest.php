<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AcademicTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year_start' => ['required', 'integer', 'min:2000', 'max:2099'],
            'semester'   => ['required', 'integer', 'in:1,2,3'],
            'starts_on'  => ['nullable', 'date'],
            'ends_on'    => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'year_start.required'    => 'School year is required.',
            'year_start.integer'     => 'School year must be a valid number.',
            'year_start.min'         => 'School year must be 2000 or later.',
            'year_start.max'         => 'School year must be 2099 or earlier.',
            'semester.required'      => 'Semester is required.',
            'semester.in'            => 'Invalid semester. Must be 1st, 2nd, or Summer.',
            'ends_on.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
