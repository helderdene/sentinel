<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AnalyticsFilterRequest extends FormRequest
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
            'preset' => ['nullable', 'in:7d,30d,90d,365d'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'incident_type_id' => ['nullable', 'integer', 'exists:incident_types,id'],
            'priority' => ['nullable', 'in:P1,P2,P3,P4'],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
        ];
    }

    /**
     * Resolve filter values, computing date range from preset if provided.
     *
     * @return array{start_date: string, end_date: string, incident_type_id?: int, priority?: string, barangay_id?: int}
     */
    public function resolvedFilters(): array
    {
        $timezone = 'Asia/Manila';
        $preset = $this->validated('preset');

        if ($preset) {
            $days = match ($preset) {
                '7d' => 7,
                '30d' => 30,
                '90d' => 90,
                '365d' => 365,
            };

            $endDate = Carbon::now($timezone)->endOfDay();
            $startDate = Carbon::now($timezone)->subDays($days)->startOfDay();
        } else {
            $startDate = $this->validated('start_date')
                ? Carbon::parse($this->validated('start_date'), $timezone)->startOfDay()
                : Carbon::now($timezone)->subDays(30)->startOfDay();

            $endDate = $this->validated('end_date')
                ? Carbon::parse($this->validated('end_date'), $timezone)->endOfDay()
                : Carbon::now($timezone)->endOfDay();
        }

        $filters = [
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $endDate->toDateTimeString(),
        ];

        if ($this->validated('incident_type_id')) {
            $filters['incident_type_id'] = (int) $this->validated('incident_type_id');
        }

        if ($this->validated('priority')) {
            $filters['priority'] = $this->validated('priority');
        }

        if ($this->validated('barangay_id')) {
            $filters['barangay_id'] = (int) $this->validated('barangay_id');
        }

        return $filters;
    }
}
