<?php

namespace App\Models;

use App\Enums\PersonnelCategory;
use Database\Factories\PersonnelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Personnel extends Model
{
    /** @use HasFactory<PersonnelFactory> */
    use HasFactory, HasUuids;

    protected $table = 'personnel';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'custom_id',
        'name',
        'gender',
        'birthday',
        'id_card',
        'phone',
        'address',
        'photo_path',
        'photo_hash',
        'photo_access_token',
        'category',
        'expires_at',
        'consent_basis',
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
            'category' => PersonnelCategory::class,
            'gender' => 'integer',
            'birthday' => 'date',
            'expires_at' => 'datetime',
            'decommissioned_at' => 'datetime',
        ];
    }

    /**
     * Scope to only active (non-decommissioned) personnel.
     *
     * @param  Builder<Personnel>  $query
     * @return Builder<Personnel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('decommissioned_at');
    }

    /**
     * Enrollments for this personnel across all cameras.
     *
     * @return HasMany<CameraEnrollment>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CameraEnrollment::class);
    }

    /**
     * Unguessable public photo URL used by cameras during enrollment.
     *
     * Returns null when no photo_access_token is set. The underlying route
     * `fras.photo.show` is registered in Plan 05; this accessor is latent
     * until then.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->photo_access_token
                ? route('fras.photo.show', ['token' => $this->photo_access_token])
                : null,
        );
    }
}
