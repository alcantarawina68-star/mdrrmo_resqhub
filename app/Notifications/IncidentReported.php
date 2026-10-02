<?php

namespace App\Notifications;

use App\Models\Incident;

/**
 * A new incident exists and is waiting on operations. This is the alert that was
 * missing entirely: previously a new incident only ever texted the person who
 * reported it, so nothing told the office that a report had arrived.
 */
class IncidentReported extends IncidentNotification
{
    public function __construct(Incident $incident, public readonly bool $alreadyVerified = false)
    {
        parent::__construct($incident);
    }

    protected function title(): string
    {
        return $this->alreadyVerified ? 'New incident reported and verified' : 'New incident reported';
    }

    protected function body(): string
    {
        $where = $this->incident->location_label ?: 'no location recorded';

        return sprintf(
            '%s via %s in %s.',
            $this->incident->incident_type->label(),
            $this->incident->source->label(),
            $where,
        );
    }
}
