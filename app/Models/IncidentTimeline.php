<?php

namespace App\Models;

use Database\Factories\IncidentTimelineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IncidentTimeline extends Model
{
    /** @use HasFactory<IncidentTimelineFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'incident_timeline';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'incident_id',
        'event_type',
        'event_data',
        'actor_type',
        'actor_id',
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
            'event_data' => 'array',
        ];
    }

    /**
     * Get the incident this timeline entry belongs to.
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * Get the actor (polymorphic).
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
