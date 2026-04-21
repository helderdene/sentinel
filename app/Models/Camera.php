<?php

namespace App\Models;

use App\Enums\CameraStatus;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\CameraFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Camera extends Model
{
    /** @use HasFactory<CameraFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'camera_id_display',
        'name',
        'location_label',
        'location',
        'status',
        'last_seen_at',
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
            'location' => Point::class,
            'status' => CameraStatus::class,
            'last_seen_at' => 'datetime',
            'decommissioned_at' => 'datetime',
        ];
    }

    /**
     * Scope to only active (non-decommissioned) cameras.
     *
     * @param  Builder<Camera>  $query
     * @return Builder<Camera>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('decommissioned_at');
    }

    /**
     * Enrollments for this camera across all personnel.
     *
     * @return HasMany<CameraEnrollment>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CameraEnrollment::class);
    }
}
