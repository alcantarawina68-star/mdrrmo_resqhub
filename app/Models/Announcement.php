<?php

namespace App\Models;

use App\Enums\AnnouncementCategory;
use App\Enums\Severity;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'category',
        'severity',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => AnnouncementCategory::class,
            'severity' => Severity::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query
            ->where('published_at', '<=', now())
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
