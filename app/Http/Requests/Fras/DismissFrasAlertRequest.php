<?php

namespace App\Http\Requests\Fras;

use App\Enums\FrasDismissReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DismissFrasAlertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('view-fras-alerts');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(array_column(FrasDismissReason::cases(), 'value'))],
            'reason_note' => ['nullable', 'string', 'max:500', 'required_if:reason,other'],
        ];
    }
}
