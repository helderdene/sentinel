<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'center_latitude' => ['required', 'numeric', 'between:-90,90'],
            'center_longitude' => ['required', 'numeric', 'between:-180,180'],
            'default_zoom' => ['required', 'integer', 'between:1,22'],
            'timezone' => ['required', 'string', 'max:64'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'emergency_hotline' => ['nullable', 'string', 'max:40'],
        ];
    }
}
