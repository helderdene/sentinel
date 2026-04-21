<?php

namespace App\Http\Requests\Admin;

use App\Enums\PersonnelCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonnelRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::enum(PersonnelCategory::class)],
            'photo' => ['required', 'file', 'mimes:jpeg,jpg', 'max:1024'],
            'consent_basis' => ['required', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
            'gender' => ['nullable', 'integer', 'in:0,1'],
            'birthday' => ['nullable', 'date'],
            'id_card' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
