<?php

namespace App\Notifications;

use App\Models\Incident;

/**
 * A unit was dispatched to an incident.
 *
 * This goes to operations roles, not to the crew, because `incidents.assigned_unit`
 * is still free text with no unit or membership model behind it — there is
 * nothing addressable to notify. Once units exist this should additionally
 * target the assigned unit's members.
 */
class IncidentAssigned extends IncidentNotification
{
    public function __construct(Incident $incident, public readonly string $unit)
    {
        parent::__construct($incident);
    }

    protected function title(): string
    {
        return 'Incident assigned to '.$this->unit;
    }

    protected function body(): string
    {
        return sprintf(
            '%s at %s was assigned to %s.',
            $this->incident->incident_type->label(),
            $this->incident->location_label ?: 'no location recorded',
            $this->unit,
        );
    }
}
