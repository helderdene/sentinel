<?php

namespace App\Http\Requests;

use App\Models\IncidentOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'outcome' => [
                'required',
                'string',
                Rule::in(IncidentOutcome::query()->active()->pluck('code')->all()),
            ],
            'hospital' => ['required_if:outcome,TRANSPORTED_TO_HOSPITAL', 'nullable', 'string'],
            'closure_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
