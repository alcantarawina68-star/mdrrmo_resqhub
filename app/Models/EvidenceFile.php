<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceFile extends Model
{
    public $timestamps = false;

    protected $table = 'evidence_files';

    protected $primaryKey = 'evidence_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * The bytes are never part of a serialized payload; they are only ever
     * streamed through the evidence image route.
     *
     * @var list<string>
     */
    protected $hidden = ['content'];

    protected $fillable = [
        'evidence_id',
        'content',
    ];

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }
}
