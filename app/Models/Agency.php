<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Agency extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'contact_phone',
        'contact_email',
        'radio_channel',
    ];

    /**
     * Get the incident types this agency handles.
     */
    public function incidentTypes(): BelongsToMany
    {
        return $this->belongsToMany(IncidentType::class, 'agency_incident_type');
    }
}
