<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FrasPurgeRun extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'started_at',
        'finished_at',
        'dry_run',
        'face_crops_purged',
        'scene_images_purged',
        'skipped_for_active_incident',
        'access_log_rows_purged',
        'error_summary',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'dry_run' => 'boolean',
            'face_crops_purged' => 'integer',
            'scene_images_purged' => 'integer',
            'skipped_for_active_incident' => 'integer',
            'access_log_rows_purged' => 'integer',
        ];
    }
}
