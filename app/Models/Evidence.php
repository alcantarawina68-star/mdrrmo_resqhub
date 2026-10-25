<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Evidence extends Model
{
    use HasFactory;

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

    /**
     * The image bytes live in the database, not on the public disk, so evidence
     * renders without the storage symlink shared hosting often refuses to create.
     */
    public function file(): HasOne
    {
        return $this->hasOne(EvidenceFile::class);
    }
}
