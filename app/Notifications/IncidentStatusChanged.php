<?php

namespace App\Notifications;

use App\Enums\IncidentStatus;
use App\Models\Incident;

/**
 * An incident moved between states — verified, rejected, marked ongoing, or
 * closed. Verification is folded in here rather than given its own class
 * because it is just another status transition, and one action should produce
 * one notification.
 */
class IncidentStatusChanged extends IncidentNotification
{
    public function __construct(
        Incident $incident,
        public readonly IncidentStatus $from,
        public readonly IncidentStatus $to,
    ) {
        parent::__construct($incident);
    }

    protected function title(): string
    {
        return 'Incident '.$this->to->label().' — '.($this->incident->incident_number ?? 'incident');
    }

    protected function body(): string
    {
        return sprintf('%s → %s.', $this->from->label(), $this->to->label());
    }
}
