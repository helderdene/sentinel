<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCitizenReportRequest extends FormRequest
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
            'incident_type_id' => ['required', 'exists:incident_types,id'],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'caller_contact' => ['required', 'string', 'max:20'],
            'caller_name' => ['nullable', 'string', 'max:100'],
            'location_text' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'barangay_id' => ['nullable', 'exists:barangays,id'],
        ];
    }
}
