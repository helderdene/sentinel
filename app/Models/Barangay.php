<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Polygon;
use Database\Factories\BarangayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    /** @use HasFactory<BarangayFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'psgc_code',
        'district',
        'city',
        'boundary',
        'population',
        'risk_level',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'boundary' => Polygon::class,
            'population' => 'integer',
        ];
    }

    /**
     * Get the incidents in this barangay.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
