<?php

namespace App\Http\Requests;

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ManualEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('manual-entry');
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
            'priority' => ['required', Rule::in(array_column(IncidentPriority::cases(), 'value'))],
            'channel' => ['required', Rule::in(array_column(IncidentChannel::cases(), 'value'))],
            'location_text' => ['required', 'string', 'max:500'],
            'caller_name' => ['nullable', 'string', 'max:255'],
            'caller_contact' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'barangay_id' => ['nullable', 'exists:barangays,id'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'incident_type_id.required' => 'Please select an incident type.',
            'incident_type_id.exists' => 'The selected incident type is invalid.',
            'priority.required' => 'Please select a priority level.',
            'channel.required' => 'Please select a reporting channel.',
            'channel.in' => 'The selected channel is invalid.',
            'location_text.required' => 'Please provide a location description.',
        ];
    }
}
