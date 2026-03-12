<?php

namespace App\Models;

use Database\Factories\IncidentMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IncidentMessage extends Model
{
    /** @use HasFactory<IncidentMessageFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'incident_id',
        'sender_type',
        'sender_id',
        'body',
        'message_type',
        'is_quick_reply',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'is_quick_reply' => 'boolean',
        ];
    }

    /**
     * Get the incident this message belongs to.
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * Get the sender (polymorphic).
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }
}
