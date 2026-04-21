<?php

namespace App\Http\Requests\Admin;

use App\Models\Camera;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCameraRequest extends FormRequest
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
        /** @var Camera|null $camera */
        $camera = $this->route('camera');
        $ignoreId = $camera?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'device_id' => ['required', 'string', 'max:64', Rule::unique('cameras', 'device_id')->ignore($ignoreId)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
