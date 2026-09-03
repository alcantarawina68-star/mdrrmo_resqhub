<?php

namespace Database\Seeders;

use App\Enums\AnnouncementCategory;
use App\Enums\IncidentStatus;
use App\Enums\Priority;
use App\Enums\Severity;
use App\Models\Announcement;
use App\Models\Incident;
use App\Models\SmsMessage;
use App\Models\StatusLog;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@resqhub.ph',
            'contact_number' => '09171230001',
        ]);

        $encoder = User::factory()->encoder()->create([
            'name' => 'Encoder User',
            'email' => 'encoder@resqhub.ph',
            'contact_number' => '09171230002',
        ]);

        $responder = User::factory()->responder()->create([
            'name' => 'Responder User',
            'email' => 'responder@resqhub.ph',
            'contact_number' => '09171230003',
        ]);

        $official = User::factory()->barangayOfficial()->create([
            'name' => 'Barangay Official',
            'email' => 'official@resqhub.ph',
            'contact_number' => '09171230004',
        ]);

        $communityUsers = User::factory()->count(12)->create();

        $reporters = collect([$admin, $encoder, ...$communityUsers]);

        $incidents = Incident::factory()->count(12)->create(['user_id' => $reporters->random()]);
        $incidents->push(...Incident::factory()->count(10)->verified()->create(['user_id' => $reporters->random()]));
        $incidents->push(...Incident::factory()->count(8)->ongoing()->create(['user_id' => $reporters->random()]));
        $incidents->push(...Incident::factory()->count(8)->resolved()->create(['user_id' => $reporters->random()]));
        $incidents->push(...Incident::factory()->count(4)->closed()->create(['user_id' => $reporters->random()]));
        $incidents->push(...Incident::factory()->count(3)->rejected()->create(['user_id' => $reporters->random()]));

        $incidents->each(function (Incident $incident) use ($admin, $encoder) {
            $actor = fake()->randomElement([$admin, $encoder]);
            $this->seedStatusLog($incident, $actor, IncidentStatus::UnderVerification);

            match ($incident->status) {
                IncidentStatus::Verified, IncidentStatus::Ongoing => $this->seedStatusLog($incident, $actor, $incident->status),
                IncidentStatus::Resolved, IncidentStatus::Closed => $this->seedStatusLog($incident, $actor, IncidentStatus::Verified, then: IncidentStatus::Resolved),
                IncidentStatus::Rejected => $this->seedStatusLog($incident, $actor, IncidentStatus::Rejected, 'Unverified report.'),
                default => null,
            };
        });

        $urgent = Incident::factory()->count(3)->ongoing()
            ->priority(Priority::Urgent)
            ->assignedTo('Rescue 117')
            ->create(['user_id' => $reporters->random()]);

        $urgent->each(fn (Incident $incident) => $this->seedStatusLog($incident, $encoder, IncidentStatus::Verified, then: IncidentStatus::Ongoing));

        Announcement::factory()->count(8)->create(['user_id' => $encoder->id]);
        Announcement::factory()->count(2)->expired()->create(['user_id' => $encoder->id]);

        Announcement::create([
            'user_id' => $admin->id,
            'title' => 'Heavy Rainfall Advisory - Camalig',
            'content' => 'PAGASA reports moderate to heavy rains over Camalig within the next 24 hours. Low-lying barangays near Mayon may experience minor flooding. Monitor advisories and prepare for possible evacuation.',
            'category' => AnnouncementCategory::Warning,
            'severity' => Severity::Urgent,
            'published_at' => now(),
            'expires_at' => now()->addDays(2),
        ]);

        SmsMessage::insert([
            [
                'phone' => '09171230002',
                'message' => 'ResQHub: New typhoon_flood report (RQ-'.now()->year.'-1000) at Sumlang. Verify and assign a unit.',
                'status' => 'sent',
                'attempts' => 1,
                'error' => null,
                'sent_at' => now()->subMinutes(40),
                'created_at' => now()->subMinutes(40),
            ],
            [
                'phone' => '09171230003',
                'message' => 'ResQHub: Assignment - RQ-'.now()->year.'-1000 (Typhoon / Flood) at Sumlang assigned to Rescue 117.',
                'status' => 'sent',
                'attempts' => 1,
                'error' => null,
                'sent_at' => now()->subMinutes(25),
                'created_at' => now()->subMinutes(25),
            ],
            [
                'phone' => '09171230005',
                'message' => 'ResQHub: Your report was received (RQ-'.now()->year.'-1001). Track it at resqhub.ph/my-reports.',
                'status' => 'failed',
                'attempts' => 2,
                'error' => 'All delivery attempts failed.',
                'sent_at' => null,
                'created_at' => now()->subMinutes(10),
            ],
        ]);
    }

    private function seedStatusLog(Incident $incident, User $actor, IncidentStatus $status, ?string $note = null, ?IncidentStatus $then = null): void
    {
        StatusLog::create([
            'incident_id' => $incident->id,
            'user_id' => $actor->id,
            'old_status' => IncidentStatus::UnderVerification->value,
            'new_status' => $status->value,
            'note' => $note ?? Str::of($status->label())->append(' status.')->toString(),
            'created_at' => $incident->reported_at->addMinutes(fake()->numberBetween(2, 30)),
        ]);

        if ($then) {
            StatusLog::create([
                'incident_id' => $incident->id,
                'user_id' => $actor->id,
                'old_status' => $status->value,
                'new_status' => $then->value,
                'note' => Str::of($then->label())->append(' status.')->toString(),
                'created_at' => $incident->reported_at->addHours(fake()->numberBetween(1, 12)),
            ]);
        }
    }
}
