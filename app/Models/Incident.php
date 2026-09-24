<?php

namespace App\Models;

use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\Priority;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'incident_type',
        'description',
        'latitude',
        'longitude',
        'location_label',
        'source',
        'caller_name',
        'caller_contact',
        'emergency_contact',
        'is_anonymous',
        'status',
        'priority',
        'assigned_unit',
        'reported_at',
        'verified_at',
        'resolved_at',
    ];

    protected $appends = ['incident_number'];

    protected function casts(): array
    {
        return [
            'incident_type' => IncidentType::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'source' => IncidentSource::class,
            'is_anonymous' => 'boolean',
            'status' => IncidentStatus::class,
            'priority' => Priority::class,
            'reported_at' => 'datetime',
            'verified_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(StatusLog::class)->latest('created_at');
    }

    public function getIncidentNumberAttribute(): string
    {
        return 'RQ-'.$this->reported_at?->year.'-'.$this->id;
    }

    /**
     * The phone number to reach for this incident: the emergency contact,
     * otherwise the caller/reporter contact.
     */
    public function emergencyContactPhone(): ?string
    {
        return $this->emergency_contact
            ?? $this->caller_contact
            ?? $this->reporter?->contact_number;
    }

    /**
     * Whether a successful SMS has already been recorded for this incident's contact.
     */
    public function hasSentContactSms(): bool
    {
        $phone = $this->emergencyContactPhone();

        if (! $phone) {
            return false;
        }

        return $this->statusLogs
            ->contains(fn (StatusLog $log) => str_starts_with((string) $log->note, 'SMS sent to '.$phone));
    }

    /**
     * Incidents visible on the public map.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereIn('status', array_column(IncidentStatus::publiclyVisible(), 'value'));
    }
}
