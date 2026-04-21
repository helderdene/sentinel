<?php

namespace App\Models;

use App\Enums\PersonnelCategory;
use Database\Factories\PersonnelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
