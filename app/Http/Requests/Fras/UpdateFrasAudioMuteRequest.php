<?php

namespace App\Http\Requests\Fras;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFrasAudioMuteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Scoped to the authenticated user; no gate beyond `auth` — preference
     * writes are self-only and bounded to the current session's user row.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'muted' => ['required', 'boolean'],
        ];
    }
}
