<?php

namespace App\Services;

use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Jobs\SendSms;
use App\Models\Evidence;
use App\Models\Incident;
use App\Models\StatusLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class IncidentService
{
    public function __construct(
        private readonly AiImageDetectionService $aiDetection,
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

        $this->notifyEmergencyContact($incident, $approved ? 'Your report has been verified.' : 'Your report was not approved.');

        return $incident;
    }

    public function updateStatus(User $actor, Incident $incident, IncidentStatus $status, ?string $note = null): Incident
    {
        if ($status === IncidentStatus::Rejected) {
            throw new RuntimeException('Use the verification flow to reject an incident.');
        }

        $incident = DB::transaction(function () use ($actor, $incident, $status, $note) {
            $attributes = [
                'status' => $status,
                'verified_at' => $incident->verified_at ?? now(),
            ];

            if ($status === IncidentStatus::Closed) {
                $attributes['resolved_at'] = $incident->resolved_at ?? now();
            }

            $incident->update($attributes);

            $this->logStatus($incident, $actor, $status, $note);

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

        $this->notifyEmergencyContact($incident, 'The incident was assigned to '.$incident->assigned_unit.'.');

        return $incident;
    }

    private function isOperationsRole(User $user): bool
    {
        return in_array($user->role?->value, UserRole::operationsRoles(), true);
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
        try {
            $result = $this->aiDetection->detect(Storage::disk('public')->path($evidence->file_path));

            $evidence->update([
                'ai_label' => $result['label'],
                'ai_is_generated' => $result['is_ai_generated'],
                'ai_score' => $result['score'],
                'ai_analyzed_at' => now(),
                'ai_error' => null,
            ]);
        } catch (RuntimeException $exception) {
            Log::error('AI image detection failed', [
                'evidence_id' => $evidence->id,
                'exception' => $exception->getMessage(),
            ]);

            $evidence->update([
                'ai_error' => $exception->getMessage(),
                'ai_analyzed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('AI image detection failed unexpectedly', [
                'evidence_id' => $evidence->id,
                'exception' => $exception->getMessage(),
            ]);

            $evidence->update([
                'ai_error' => 'AI detection failed.',
                'ai_analyzed_at' => now(),
            ]);
        }
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
