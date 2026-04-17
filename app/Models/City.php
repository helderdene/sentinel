<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'name',
        'province',
        'country',
        'center_latitude',
        'center_longitude',
        'default_zoom',
        'timezone',
        'contact_number',
        'emergency_hotline',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'center_latitude' => 'decimal:7',
            'center_longitude' => 'decimal:7',
            'default_zoom' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'name' => 'Butuan City',
            'province' => 'Agusan del Norte',
            'country' => 'Philippines',
            'center_latitude' => 8.9475,
            'center_longitude' => 125.5406,
            'default_zoom' => 13,
            'timezone' => 'Asia/Manila',
        ]);
    }
}
