<?php

namespace App\Models;

use App\Enums\FrasAccessAction;
use App\Enums\FrasAccessSubject;
use Database\Factories\FrasAccessLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrasAccessLog extends Model
{
    /** @use HasFactory<FrasAccessLogFactory> */
    use HasFactory, HasUuids;

    /**
     * Explicit table name — pluralizer would otherwise produce fras_access_logs.
     */
    protected $table = 'fras_access_log';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'actor_user_id',
        'ip_address',
        'user_agent',
        'subject_type',
        'subject_id',
        'action',
        'accessed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_type' => FrasAccessSubject::class,
            'action' => FrasAccessAction::class,
            'accessed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the user who performed this access.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
