<?php

namespace App\Models;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'callsign',
        'type',
        'agency',
        'crew_capacity',
        'status',
        'coordinates',
        'shift',
        'notes',
        'decommissioned_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coordinates' => Point::class,
            'status' => UnitStatus::class,
            'type' => UnitType::class,
            'crew_capacity' => 'integer',
            'decommissioned_at' => 'datetime',
        ];
    }

    /**
     * Scope to only active (non-decommissioned) units.
     *
     * @param  Builder<Unit>  $query
     * @return Builder<Unit>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('decommissioned_at');
    }

    /**
     * Scope alias for clarity.
     *
     * @param  Builder<Unit>  $query
     * @return Builder<Unit>
     */
    public function scopeCommissioned(Builder $query): Builder
    {
        return $this->scopeActive($query);
    }

    /**
     * Serialize coordinates as {lat, lng} for the frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->coordinates instanceof Point) {
            $array['coordinates'] = [
                'lat' => $this->coordinates->getLatitude(),
                'lng' => $this->coordinates->getLongitude(),
            ];
        }

        return $array;
    }

    /**
     * Get the users (responders) assigned to this unit.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get incidents currently assigned to this unit (legacy single FK).
     */
    public function currentIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'assigned_unit', 'id');
    }

    /**
     * Get all active incidents via the incident_unit pivot.
     */
    public function activeIncidents(): BelongsToMany
    {
        return $this->belongsToMany(Incident::class, 'incident_unit')
            ->using(IncidentUnit::class)
            ->withPivot(['assigned_at', 'acknowledged_at', 'unassigned_at', 'assigned_by'])
            ->wherePivotNull('unassigned_at');
    }
}
