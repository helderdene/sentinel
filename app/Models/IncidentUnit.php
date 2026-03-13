<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class IncidentUnit extends Pivot
{
    public $incrementing = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'incident_unit';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }
}
