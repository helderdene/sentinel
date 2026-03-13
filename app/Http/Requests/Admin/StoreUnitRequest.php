<?php

namespace App\Http\Requests\Admin;

use App\Enums\UnitType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_column(UnitType::cases(), 'value'))],
            'callsign' => ['nullable', 'string', 'max:50'],
            'agency' => ['required', 'string', 'max:50'],
            'crew_capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::in(['AVAILABLE', 'OFFLINE'])],
            'shift' => ['nullable', Rule::in(['day', 'night'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'crew_ids' => ['nullable', 'array'],
            'crew_ids.*' => ['exists:users,id'],
        ];
    }
}
