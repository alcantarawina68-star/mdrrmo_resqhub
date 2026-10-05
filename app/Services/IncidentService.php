<?php

namespace App\Services;

use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendSms;
use App\Models\Evidence;
use App\Models\Incident;
use App\Models\StatusLog;
use App\Models\User;
use App\Notifications\IncidentAssigned;
use App\Notifications\IncidentNotification;
use App\Notifications\IncidentReported;
use App\Notifications\IncidentStatusChanged;
use App\Support\AiImageDetector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class IncidentService
{
    private const HUMAN_LABELS = ['human', 'real', 'original', 'photo', 'not_ai'];

    private const AI_LABELS = ['ai', 'fake', 'generated', 'synthetic', 'digital'];

    public function __construct(
        private readonly SmsService $sms,
    ) {}

    public function createOnline(User $reporter, array $data): Incident
    {
        $attachment = $data['evidence'] ?? null;
        unset($data['evidence']);

        $assignedUnit = $data['assigned_unit'] ?? null;
        unset($data['assigned_unit']);

        $autoVerified = $this->isOperationsRole($reporter);
        $status = $autoVerified ? IncidentStatus::Verified : IncidentStatus::UnderVerification;

        $incident = DB::transaction(function () use ($reporter, $data, $attachment, $status, $autoVerified, $assignedUnit) {
            $incident = $reporter->incidents()->create([
                ...$data,
                'source' => IncidentSource::Online,
                'status' => $status,
                'verified_at' => $autoVerified ? now() : null,
                'assigned_unit' => $autoVerified ? $assignedUnit : null,
                'reported_at' => now(),
            ]);

            $this->attachEvidence($incident, $attachment);
            $this->logStatus(
                $incident,
                $reporter,
                $status,
                $autoVerified ? 'Incident submitted online and verified.' : 'Incident submitted online.',
            );

            $this->notifyOperations(
                new IncidentReported($incident, $autoVerified),
                $reporter,
            );

            return $incident;
        });

        $this->notifyEmergencyContact($incident, $autoVerified ? 'Your report was received and is now verified.' : 'Your report was received.');

        return $incident;
    }

    public function createCallerBased(User $encoder, array $data): Incident
    {
        $assignedUnit = $data['assigned_unit'] ?? null;
        unset($data['assigned_unit']);

        $autoVerified = $this->isOperationsRole($encoder);
        $status = $autoVerified ? IncidentStatus::Verified : IncidentStatus::UnderVerification;

        $incident = DB::transaction(function () use ($encoder, $data, $status, $autoVerified, $assignedUnit) {
            $incident = $encoder->incidents()->create([
                ...$data,
                'source' => IncidentSource::CallerBased,
                'status' => $status,
                'verified_at' => $autoVerified ? now() : null,
                'assigned_unit' => $autoVerified ? $assignedUnit : null,
                'reported_at' => now(),
            ]);

            $this->logStatus(
                $incident,
                $encoder,
                $status,
                $autoVerified ? 'Caller-based report encoded and verified.' : 'Caller-based report encoded.',
            );

            $this->notifyOperations(
                new IncidentReported($incident, $autoVerified),
                $encoder,
            );

            return $incident;
        });

        $this->notifyEmergencyContact($incident, $autoVerified ? 'Your report was received and is now verified.' : 'Your report was received.');

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
        $previousStatus = $incident->status;

        $incident = DB::transaction(function () use ($verifier, $incident, $approved, $notes, $assignedUnit, $newStatus, $previousStatus) {
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

            $this->notifyOperations(
                new IncidentStatusChanged($incident, $previousStatus, $newStatus),
                $verifier,
            );

            return $incident;
        });

        $this->notifyEmergencyContact($incident, $approved ? 'Your report has been verified.' : 'Your report was not approved.');

        return $incident;
    }

    public function updateStatus(User $actor, Incident $incident, IncidentStatus $status, ?string $note = null): Incident
    {
        if ($status === IncidentStatus::Rejected) {
            throw new RuntimeException('Use the verification flow to reject an incident.');
        }

        $previousStatus = $incident->status;

        $incident = DB::transaction(function () use ($actor, $incident, $status, $note, $previousStatus) {
            $attributes = [
                'status' => $status,
                'verified_at' => $incident->verified_at ?? now(),
            ];

            if ($status === IncidentStatus::Closed) {
                $attributes['resolved_at'] = $incident->resolved_at ?? now();
            }

            $incident->update($attributes);

            $this->logStatus($incident, $actor, $status, $note);

            if ($previousStatus !== $status) {
                $this->notifyOperations(
                    new IncidentStatusChanged($incident, $previousStatus, $status),
                    $actor,
                );
            }

            return $incident;
        });

        $this->notifyEmergencyContact($incident, 'Status is now '.$incident->status->label().'.');

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
        $alreadyAssigned = $incident->assigned_unit === $unit;

        $incident = DB::transaction(function () use ($actor, $incident, $unit, $alreadyAssigned) {
            $incident->update(['assigned_unit' => $unit]);

            $this->logStatus(
                $incident,
                $actor,
                $incident->status,
                "Assigned to: {$unit}.",
            );

            if (! $alreadyAssigned) {
                $this->notifyOperations(new IncidentAssigned($incident, $unit), $actor);
            }

            return $incident;
        });

        $this->notifyEmergencyContact($incident, 'The incident was assigned to '.$incident->assigned_unit.'.');

        return $incident;
    }

    /**
     * Write an in-app alert for every active operations user.
     *
     * Recipients are the operations roles (admin, encoder, superadmin) via the
     * same enum helper the route and Blade gates use. The actor is skipped so
     * staff are not notified of their own clicks, and deactivated accounts are
     * skipped so nobody is paged into a login they cannot use.
     *
     * Sent synchronously inside the caller's transaction: the database channel
     * only inserts rows, and a notification that outlives a rolled-back change
     * would be worse than a missing one.
     */
    private function notifyOperations(IncidentNotification $notification, User $actor): void
    {
        $recipients = User::query()
            ->whereIn('role', UserRole::operationsRoles())
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($actor->getKey())
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }

    private function isOperationsRole(User $user): bool
    {
        return $user->isOperationsRole();
    }

    private function attachEvidence(Incident $incident, ?UploadedFile $file): void
    {
        if ($file === null) {
            return;
        }

        $path = $file->store('evidence', 'public');

        $evidence = Evidence::create([
            'incident_id' => $incident->id,
            'file_path' => $path,
            'file_type' => $file->getMimeType() ?: $file->guessExtension(),
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);

        $this->analyzeEvidence($evidence);
    }

    private function analyzeEvidence(Evidence $evidence): void
    {
        $result = app(AiImageDetector::class)->predict(
            Storage::disk('public')->path($evidence->file_path),
            $evidence->file_type,
        );

        if ($result['success'] !== true || ($isAi = $this->labelIsAi($result['label'] ?? '')) === null) {
            $error = $result['success'] === true ? 'Invalid AI detection response.' : $result['error'];

            Log::error('AI image detection failed', [
                'evidence_id' => $evidence->id,
                'exception' => $error,
            ]);

            $evidence->update([
                'ai_error' => $error,
                'ai_analyzed_at' => now(),
            ]);

            return;
        }

        $evidence->update([
            'ai_label' => $result['label'],
            'ai_is_generated' => $isAi,
            'ai_score' => $result['confidence'],
            'ai_analyzed_at' => now(),
            'ai_error' => null,
        ]);
    }

    /**
     * Classify a Hugging Face label, or null when it matches neither side.
     */
    private function labelIsAi(string $label): ?bool
    {
        $haystack = strtolower($label);

        foreach (self::HUMAN_LABELS as $humanLabel) {
            if (str_contains($haystack, $humanLabel)) {
                return false;
            }
        }

        foreach (self::AI_LABELS as $aiLabel) {
            if (str_contains($haystack, $aiLabel)) {
                return true;
            }
        }

        return null;
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

    private function notifyEmergencyContact(Incident $incident, string $event): void
    {
        $phone = $incident->emergencyContactPhone();

        if (! $phone) {
            return;
        }

        SendSms::dispatch($phone, $this->contactMessage($incident, $event, filled($incident->emergency_contact)), $incident->id);
    }

    /**
     * Send the emergency-contact SMS right now (no queue) and record the delivery
     * in the incident's status history.
     */
    public function resendContactNotification(Incident $incident): bool
    {
        $phone = $incident->emergencyContactPhone();

        if (! $phone || $incident->hasSentContactSms()) {
            return false;
        }

        return $this->sms->sendForIncident($phone, $this->contactMessage($incident, 'You are the designated contact and will receive updates about this incident.', filled($incident->emergency_contact)), $incident);
    }

    private function contactMessage(Incident $incident, string $event, bool $isEmergencyContact): string
    {
        $person = $this->personName($incident);
        $type = $incident->incident_type->label();
        $number = $incident->incident_number;

        if ($isEmergencyContact) {
            $intro = $person
                ? "{$person} listed this number as their emergency contact for a {$type} incident ({$number})."
                : "This number was registered as the emergency contact for a {$type} incident ({$number}).";
        } else {
            $intro = $person
                ? "{$person} is involved in a {$type} incident ({$number})."
                : "This number is listed for a {$type} incident ({$number}).";
        }

        return 'ResQHub: '.$intro.' '.$event.' Track updates here: '.site_setting('website', 'resqhub.ph').'/my-reports.';
    }

    private function personName(Incident $incident): ?string
    {
        if ($incident->source === IncidentSource::CallerBased) {
            return $incident->caller_name;
        }

        if ($incident->reporter?->role?->value === UserRole::CommunityUser->value) {
            return $incident->reporter->name;
        }

        return null;
    }
}
