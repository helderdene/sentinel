<?php

namespace App\Http\Requests;

use App\Enums\IncidentOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveIncidentRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'outcome' => [
                'required',
                'string',
                Rule::in(array_column(IncidentOutcome::cases(), 'value')),
            ],
            'hospital' => ['required_if:outcome,TRANSPORTED_TO_HOSPITAL', 'nullable', 'string'],
            'closure_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
