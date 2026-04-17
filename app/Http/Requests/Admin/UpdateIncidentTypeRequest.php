<?php

namespace App\Http\Requests\Admin;

use App\Enums\IncidentPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentTypeRequest extends FormRequest
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
            'incident_category_id' => ['required', 'exists:incident_categories,id'],
            'category' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('incident_types', 'code')->ignore($this->route('incident_type'))],
            'default_priority' => ['required', Rule::in(array_column(IncidentPriority::cases(), 'value'))],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'show_in_public_app' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
