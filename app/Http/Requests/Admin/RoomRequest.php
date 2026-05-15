<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoomType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roomId = $this->route('room')?->id;

        return [
            'code'          => ['required', 'string', 'max:20', Rule::unique('tbl_rooms', 'code')->ignore($roomId)],
            'name'          => ['required', 'string', 'max:100'],
            'room_type'     => ['required', 'string', Rule::in(array_column(RoomType::cases(), 'value'))],
            'floor'         => ['nullable', 'integer', 'min:1', 'max:20'],
            'capacity'      => ['nullable', 'integer', 'min:1', 'max:500'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active'     => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'      => 'Room code is required.',
            'code.max'           => 'Room code must not exceed 20 characters.',
            'code.unique'        => 'This room code is already in use.',
            'name.required'      => 'Room name is required.',
            'name.max'           => 'Room name must not exceed 100 characters.',
            'room_type.required' => 'Room type is required.',
            'room_type.in'       => 'Invalid room type selected.',
            'floor.min'          => 'Floor must be at least 1.',
            'floor.max'          => 'Floor must not exceed 20.',
            'capacity.min'       => 'Capacity must be at least 1.',
            'capacity.max'       => 'Capacity must not exceed 500.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'     => $this->boolean('is_active', true),
            'display_order' => $this->input('display_order') ?? 0,
        ]);
    }
}
