<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class RoomOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id'    => ['required', 'integer', 'exists:tbl_rooms,id'],
            'status'     => ['required', 'string', Rule::in(['maintenance', 'unavailable', 'reserved'])],
            'reason'     => ['nullable', 'string', 'max:2000'],
            'starts_at'  => ['required', 'date'],
            'ends_at'    => ['nullable', 'date', 'after:starts_at'],
            'indefinite' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'indefinite' => filter_var($this->input('indefinite', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
