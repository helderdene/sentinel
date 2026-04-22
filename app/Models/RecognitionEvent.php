<?php

namespace App\Models;

use App\Enums\FrasDismissReason;
use App\Enums\RecognitionSeverity;
use Database\Factories\RecognitionEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecognitionEvent extends Model
{
    /** @use HasFactory<RecognitionEventFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'camera_id',
        'personnel_id',
        'incident_id',
        'record_id',
        'custom_id',
        'camera_person_id',
        'verify_status',
        'person_type',
        'similarity',
        'is_real_time',
        'name_from_camera',
        'facesluice_id',
        'id_card',
        'phone',
        'is_no_mask',
        'target_bbox',
        'captured_at',
        'received_at',
        'face_image_path',
        'scene_image_path',
        'raw_payload',
        'severity',
        'acknowledged_by',
        'acknowledged_at',
        'dismissed_at',
        'dismissed_by',
        'dismiss_reason',
        'dismiss_reason_note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'target_bbox' => 'array',
            'captured_at' => 'datetime',
            'received_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'severity' => RecognitionSeverity::class,
            'dismiss_reason' => FrasDismissReason::class,
            'is_real_time' => 'boolean',
            'similarity' => 'decimal:2',
            'verify_status' => 'integer',
            'person_type' => 'integer',
            'is_no_mask' => 'integer',
        ];
    }

    /**
     * Get the camera that produced this recognition event.
     */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    /**
     * Get the personnel matched in this recognition event.
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    /**
     * Get the incident this event is linked to (Phase 21 sets this).
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * Get the user who acknowledged this event.
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Get the user who dismissed this event (Phase 22).
     */
    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }
}
