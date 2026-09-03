<?php

namespace App\Services;

use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\Priority;
use App\Enums\UserRole;
use App\Jobs\SendSms;
use App\Models\Incident;
use App\Models\StatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IncidentService
{
    public function createOnline(User $reporter, array $data): Incident
    {
        $incident = DB::transaction(function () use ($reporter, $data) {
            $incident = $reporter->incidents()->create([
                ...$data,
                'source' => IncidentSource::Online,
                'status' => IncidentStatus::UnderVerification,
                'reported_at' => now(),
            ]);

            $this->logStatus($incident, $reporter, IncidentStatus::UnderVerification, 'Incident submitted online.');

            return $incident;
        });

        $this->notifyPersonnel($incident);
        $this->notifyCaller($incident, $data['contact_number'] ?? $reporter->contact_number);

        return $incident;
    }

    public function createCallerBased(User $encoder, array $data): Incident
    {
        $incident = DB::transaction(function () use ($encoder, $data) {
            $incident = $encoder->incidents()->create([
                ...$data,
                'source' => IncidentSource::CallerBased,
                'status' => IncidentStatus::UnderVerification,
                'reported_at' => now(),
            ]);

            $this->logStatus($incident, $encoder, IncidentStatus::UnderVerification, 'Caller-based report encoded.');

            return $incident;
        });

        $this->notifyPersonnel($incident);

        return $incident;
    }

    /**
     * Approve or reject an incident that is under verification.
     */
    public function processVerification(User $verifier, Incident $incident, bool $approved, ?string $notes = null, ?string $assignedUnit = null): Incident
    {
        if ($incident->status !== IncidentStatus::UnderVerification) {
            throw new RuntimeException('Only incidents under verification can be processed.');
        }

        $newStatus = $approved ? IncidentStatus::Verified : IncidentStatus::Rejected;

        $incident = DB::transaction(function () use ($verifier, $incident, $approved, $notes, $assignedUnit, $newStatus) {
            $incident->update([
                'status' => $newStatus,
                'verified_at' => $approved ? now() : $incident->verified_at,
                'assigned_unit' => $approved && $assignedUnit ? $assignedUnit : $incident->assigned_unit,
            ]);

            $this->logStatus(
                $incident,
                $verifier,
                $newStatus,
                $notes ?: ($approved ? 'Incident verified.' : 'Incident rejected after verification.'),
            );

            return $incident;
        });

        $this->notifyReporter($incident);

        if ($approved) {
            $this->notifyAssignedResponders($incident);
            $this->notifyPriorityAlert($incident);
        }

        return $incident;
    }

    public function updateStatus(User $actor, Incident $incident, IncidentStatus $status, ?string $note = null): Incident
    {
        if ($status === IncidentStatus::Rejected) {
            throw new RuntimeException('Use the verification flow to reject an incident.');
        }

        $resolvedStates = [IncidentStatus::Resolved, IncidentStatus::Closed];

        $incident = DB::transaction(function () use ($actor, $incident, $status, $note, $resolvedStates) {
            $incident->update([
                'status' => $status,
                'verified_at' => $status !== IncidentStatus::New ? ($incident->verified_at ?? now()) : null,
                'resolved_at' => in_array($status, $resolvedStates, true) ? now() : null,
            ]);

            $this->logStatus($incident, $actor, $status, $note);

            return $incident;
        });

        $this->notifyReporter($incident);

        if ($incident->assigned_unit) {
            $this->notifyAssignedResponders($incident);
        }

        return $incident;
    }

    public function updateDetails(User $actor, Incident $incident, array $data): Incident
    {
        $incident = DB::transaction(function () use ($actor, $incident, $data) {
            $incident->update($data);

            $this->logStatus(
                $incident,
                $actor,
                $incident->status,
                'Incident details updated.',
            );

            return $incident;
        });

        return $incident;
    }

    public function assignUnit(User $actor, Incident $incident, string $unit): Incident
    {
        $incident = DB::transaction(function () use ($actor, $incident, $unit) {
            $incident->update(['assigned_unit' => $unit]);

            $this->logStatus(
                $incident,
                $actor,
                $incident->status,
                "Assigned to: {$unit}.",
            );

            return $incident;
        });

        $this->notifyAssignedResponders($incident);

        return $incident;
    }

    private function logStatus(Incident $incident, User $actor, IncidentStatus $newStatus, ?string $note = null): void
    {
        StatusLog::create([
            'incident_id' => $incident->id,
            'user_id' => $actor->id,
            'old_status' => $incident->getOriginal('status'),
            'new_status' => $newStatus->value,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    private function notifyPersonnel(Incident $incident): void
    {
        $personnel = User::query()
            ->active()
            ->whereIn('role', UserRole::operationsRoles())
            ->get();

        $message = "ResQHub: New {$incident->incident_type->label()} report ({$incident->incident_number}) at {$incident->location_label}.";
        $this->dispatchToUsers($personnel, $message);
    }

    private function notifyReporter(Incident $incident): void
    {
        $reporter = $incident->reporter;

        if (! $reporter?->contact_number) {
            return;
        }

        $message = "ResQHub: Your incident {$incident->incident_number} is now {$incident->status->label()}. Status updates will be sent here.";

        $this->dispatchToUsers(collect([$reporter]), $message);
    }

    private function notifyAssignedResponders(Incident $incident): void
    {
        $responders = User::query()
            ->active()
            ->where('role', UserRole::Responder->value)
            ->get();

        if ($responders->isEmpty()) {
            return;
        }

        $message = "ResQHub: Assignment - {$incident->incident_number} ({$incident->incident_type->label()}) at {$incident->location_label} assigned to {$incident->assigned_unit}.";

        $this->dispatchToUsers($responders, $message);
    }

    private function notifyPriorityAlert(Incident $incident): void
    {
        if ($incident->priority !== Priority::Urgent) {
            return;
        }

        $staff = User::query()
            ->active()
            ->whereIn('role', [
                UserRole::Admin->value,
                UserRole::Encoder->value,
                UserRole::Responder->value,
            ])
            ->get();

        $message = "URGENT: Verified {$incident->incident_type->label()} ({$incident->incident_number}) at {$incident->location_label} requires immediate attention.";

        $this->dispatchToUsers($staff, $message);
    }

    private function notifyCaller(Incident $incident, ?string $phone): void
    {
        if (! $phone) {
            return;
        }

        $message = "ResQHub: Your report was received ({$incident->incident_number}). Track it at resqhub.ph/my-reports.";

        SendSms::dispatch($phone, $message);
    }

    private function dispatchToUsers(iterable $users, string $message): void
    {
        foreach ($users as $user) {
            if ($user->contact_number) {
                SendSms::dispatch($user->contact_number, $message);
            }
        }
    }
}
