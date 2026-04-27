<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class IncidentOutcome extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'code',
        'label',
        'description',
        'applicable_categories',
        'is_universal',
        'requires_vitals',
        'requires_hospital',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applicable_categories' => 'array',
            'is_universal' => 'boolean',
            'requires_vitals' => 'boolean',
            'requires_hospital' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Active outcomes only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Outcomes appropriate for a given incident-type category. Universal
     * outcomes (e.g. False Alarm) always appear; category-scoped outcomes
     * only appear when their `applicable_categories` JSON list contains
     * the supplied category. When $category is null/unknown the full
     * active set is returned so the responder still has a complete list.
     *
     * @return Collection<int, self>
     */
    public static function forCategory(?string $category): Collection
    {
        $query = static::query()->active();

        if ($category !== null && $category !== '') {
            $query->where(function (Builder $q) use ($category): void {
                $q->where('is_universal', true)
                    ->orWhereJsonContains('applicable_categories', $category);
            });
        }

        return $query
            ->orderBy('is_universal') // category-specific first, universal last
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /**
     * Determine if this outcome requires vitals to be recorded before
     * the incident may be resolved.
     */
    public function requiresVitals(): bool
    {
        return (bool) $this->requires_vitals;
    }
}
