<?php

namespace App\Http\Resources;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Incident */
class IncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incident_number' => $this->incident_number,
            'incident_type' => $this->incident_type?->value,
            'incident_type_label' => $this->incident_type?->label(),
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_label' => $this->location_label,
            'source' => $this->source?->value,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'assigned_unit' => $this->assigned_unit,
            'is_anonymous' => $this->is_anonymous,
            'caller_name' => $this->caller_name,
            'caller_contact' => $this->caller_contact,
            'emergency_contact' => $this->emergency_contact,
            'reported_at' => $this->reported_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'reporter' => $this->when($this->relationLoaded('reporter'), fn () => [
                'id' => $this->reporter?->id,
                'name' => $this->is_anonymous ? 'Anonymous' : $this->reporter?->name,
                'contact_number' => $this->is_anonymous ? null : $this->reporter?->contact_number,
            ]),
            'evidence' => EvidenceResource::collection($this->whenLoaded('evidence')),
        ];
    }
}
