<?php

namespace App\Models;

use App\Enums\CameraEnrollmentStatus;
use Database\Factories\CameraEnrollmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CameraEnrollment extends Model
{
    /** @use HasFactory<CameraEnrollmentFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'camera_id',
        'personnel_id',
        'status',
        'enrolled_at',
        'photo_hash',
        'last_error',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CameraEnrollmentStatus::class,
            'enrolled_at' => 'datetime',
        ];
    }

    /**
     * Get the camera this enrollment belongs to.
     */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    /**
     * Get the personnel this enrollment belongs to.
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
