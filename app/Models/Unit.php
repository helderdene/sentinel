<?php

namespace App\Models;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        ];
    }

    /**
     * Get the users (responders) assigned to this unit.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get incidents currently assigned to this unit.
     */
    public function currentIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'assigned_unit', 'id');
    }
}
