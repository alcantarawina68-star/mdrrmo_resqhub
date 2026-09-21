<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'incident_id',
        'file_path',
        'file_type',
        'original_name',
        'file_size',
        'uploaded_at',
        'ai_label',
        'ai_is_generated',
        'ai_score',
        'ai_analyzed_at',
        'ai_error',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'ai_is_generated' => 'boolean',
            'ai_score' => 'float',
            'ai_analyzed_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
