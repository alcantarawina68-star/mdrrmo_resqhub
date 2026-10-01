<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Notifications\Notification;

/**
 * Base for the in-app notifications that tell MDRRMO staff that an incident
 * needs attention.
 *
 * These are deliberately database-only and are written inside the same
 * transaction as the incident change that caused them, so a rolled-back change
 * never leaves an alert behind and a missing queue worker never drops one. SMS
 * remains the external channel; this is the in-app copy the PRD calls for.
 *
 * `data` carries plain scalars only — never the Incident model — so the payload
 * stays readable in the database and survives the incident being deleted (the
 * index view tolerates a missing incident).
 */
abstract class IncidentNotification extends Notification
{
    public function __construct(public readonly Incident $incident) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, url: string, incidentId: int, incidentNumber: string|null, status: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => route('dashboard.incidents.show', $this->incident),
            'incidentId' => $this->incident->id,
            'incidentNumber' => $this->incident->incident_number,
            'status' => $this->incident->status->value,
        ];
    }

    abstract protected function title(): string;

    abstract protected function body(): string;
}
