<?php

namespace App\Models;

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'incident_no',
        'incident_type_id',
        'priority',
        'status',
        'channel',
        'location_text',
        'coordinates',
        'barangay_id',
        'caller_name',
        'caller_contact',
        'raw_message',
        'notes',
        'assigned_unit',
        'dispatched_at',
        'acknowledged_at',
        'en_route_at',
        'on_scene_at',
        'resolved_at',
        'outcome',
        'hospital',
        'scene_time_sec',
        'checklist_pct',
        'vitals',
        'assessment_tags',
        'closure_notes',
        'report_pdf_url',
        'created_by',
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
            'priority' => IncidentPriority::class,
            'status' => IncidentStatus::class,
            'channel' => IncidentChannel::class,
            'vitals' => 'array',
            'assessment_tags' => 'array',
            'dispatched_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'en_route_at' => 'datetime',
            'on_scene_at' => 'datetime',
            'resolved_at' => 'datetime',
            'scene_time_sec' => 'integer',
            'checklist_pct' => 'integer',
        ];
    }

    /**
     * Boot the model and auto-generate incident_no.
     */
    protected static function booted(): void
    {
        static::creating(function (Incident $incident) {
            if (empty($incident->incident_no)) {
                $incident->incident_no = static::generateIncidentNumber();
            }
        });
    }

    /**
     * Generate a unique incident number in INC-YYYY-NNNNN format.
     */
    protected static function generateIncidentNumber(): string
    {
        $year = now()->year;
        $prefix = "INC-{$year}-";

        $lastNumber = DB::table('incidents')
            ->where('incident_no', 'like', "{$prefix}%")
            ->orderByDesc('incident_no')
            ->value('incident_no');

        if ($lastNumber) {
            $sequence = (int) substr($lastNumber, -5) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Serialize coordinates as {lat, lng} for the frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->coordinates instanceof Point) {
            $array['coordinates'] = [
                'lat' => $this->coordinates->getLatitude(),
                'lng' => $this->coordinates->getLongitude(),
            ];
        }

        return $array;
    }

    /**
     * Get the incident type.
     */
    public function incidentType(): BelongsTo
    {
        return $this->belongsTo(IncidentType::class);
    }

    /**
     * Get the barangay where the incident occurred.
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /**
     * Get the user who created this incident.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the timeline entries for this incident.
     */
    public function timeline(): HasMany
    {
        return $this->hasMany(IncidentTimeline::class);
    }

    /**
     * Get the messages for this incident.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(IncidentMessage::class);
    }

    /**
     * Get the assigned unit (legacy single-unit FK, kept for backward compatibility).
     */
    public function assignedUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'assigned_unit', 'id');
    }

    /**
     * Get all actively assigned units via the incident_unit pivot.
     */
    public function assignedUnits(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'incident_unit')
            ->using(IncidentUnit::class)
            ->withPivot(['assigned_at', 'acknowledged_at', 'unassigned_at', 'assigned_by'])
            ->wherePivotNull('unassigned_at');
    }
}
