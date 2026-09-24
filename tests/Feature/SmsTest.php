<?php

use App\Enums\IncidentType;
use App\Enums\Priority;
use App\Jobs\SendSms;
use App\Models\Incident;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Semaphore\SemaphoreClient;

function withSemaphoreDriver(?string $sender = 'RESQHUB'): void
{
    config([
        'services.sms.driver' => 'semaphore',
        'services.sms.semaphore' => [
            'url' => 'https://api.semaphore.co/api/v4',
            'key' => 'secret-key',
            'sender' => $sender,
            'timeout' => 15,
        ],
    ]);
}

test('the semaphore driver sends through the client and marks the message sent', function () {
    withSemaphoreDriver();
    $this->mock(SemaphoreClient::class)
        ->shouldReceive('send')
        ->with('09171234567', 'Hello')
        ->andReturn(json_encode([['message_id' => 123, 'status' => 'Queued']]));

    $sent = app(SmsService::class)->send('09171234567', 'Hello');

    expect($sent)->toBeTrue();

    $log = SmsMessage::first();

    expect($log)->not->toBeNull();
    expect($log->phone)->toBe('09171234567');
    expect($log->status)->toBe('sent');
    expect($log->attempts)->toBe(1);
    expect($log->sent_at)->not->toBeNull();
});

test('the semaphore driver records a failure when the client throws', function () {
    withSemaphoreDriver();
    $this->mock(SemaphoreClient::class)
        ->shouldReceive('send')
        ->andThrow(new RuntimeException('Semaphore SMS failed (HTTP 401)'));

    $sent = app(SmsService::class)->send('09171234567', 'Hello');

    expect($sent)->toBeFalse();

    $log = SmsMessage::first();

    expect($log->status)->toBe('failed');
    expect($log->attempts)->toBe(2);
    expect($log->sent_at)->toBeNull();
});

test('the semaphore driver records a failure when the response body has an error', function () {
    withSemaphoreDriver();
    $this->mock(SemaphoreClient::class)
        ->shouldReceive('send')
        ->andReturn(json_encode(['error' => 'Invalid sender name.']));

    $sent = app(SmsService::class)->send('09171234567', 'Hello');

    expect($sent)->toBeFalse();

    $log = SmsMessage::first();

    expect($log->status)->toBe('failed');
    expect($log->attempts)->toBe(2);
});

test('an unsupported SMS driver records a failure and does not throw', function () {
    config(['services.sms.driver' => 'twilio']);

    $sent = app(SmsService::class)->send('09171234567', 'Hello');

    expect($sent)->toBeFalse();
    expect(SmsMessage::first()->status)->toBe('failed');
});

test('the semaphore client is built with the api key and configured sender name', function () {
    withSemaphoreDriver('RESQHUB');

    $client = app(SemaphoreClient::class);

    expect($client->apikey)->toBe('secret-key');
    expect($client->senderName)->toBe('RESQHUB');
});

test('the semaphore client omits a sender name when none is configured', function () {
    withSemaphoreDriver(null);

    $client = app(SemaphoreClient::class);

    expect($client->apikey)->toBe('secret-key');
    expect($client->senderName)->toBeNull();
});

test('the semaphore client targets the v4 messages endpoint under the configured base url', function () {
    withSemaphoreDriver();

    $client = app(SemaphoreClient::class);
    $reflection = new ReflectionProperty(SemaphoreClient::class, 'client');
    $guzzle = $reflection->getValue($client);

    expect((string) $guzzle->getConfig('base_uri'))->toBe('https://api.semaphore.co/api/v4/');
});

test('submitting an online report sends an SMS only to its emergency contact', function () {
    Queue::fake([SendSms::class]);

    User::factory()->admin()->create();
    User::factory()->responder()->create();
    $user = User::factory()->communityUser()->create(['contact_number' => '09111111111']);

    $this->actingAs($user)
        ->post(route('report.store'), [
            'incident_type' => IncidentType::Fire->value,
            'description' => 'A house fire was spotted near the barangay hall spreading quickly.',
            'latitude' => 18.2756,
            'longitude' => 121.6756,
            'priority' => Priority::High->value,
            'emergency_contact' => '09179998888',
        ])
        ->assertRedirect(route('my-reports'));

    Queue::assertPushed(SendSms::class, 1);
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->phone === '09179998888'
        && str_contains($job->message, $user->name)
        && str_contains($job->message, 'Fire')
        && str_contains($job->message, 'listed this number as their emergency contact'));
});

test('a caller-based report without an emergency contact falls back to the caller contact', function () {
    Queue::fake([SendSms::class]);

    $encoder = User::factory()->encoder()->create();

    $this->actingAs($encoder)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('dashboard.caller.store'), [
            'incident_type' => IncidentType::TyphoonFlood->value,
            'description' => 'Rising floodwater entered the houses along the riverbank quickly.',
            'latitude' => 18.2756,
            'longitude' => 121.6756,
            'location_label' => 'Balogo',
            'priority' => Priority::High->value,
            'caller_name' => 'Maria Santos',
            'caller_contact' => '09179876543',
        ])
        ->assertRedirect(route('dashboard.incidents.show', Incident::first()));

    Queue::assertPushed(SendSms::class, 1);
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->phone === '09179876543'
        && str_contains($job->message, 'Maria Santos')
        && str_contains($job->message, 'involved in a Typhoon / Flood incident'));
});

test('an auto-verified online report sends a single combined received-and-verified SMS', function () {
    Queue::fake([SendSms::class]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('report.store'), [
            'incident_type' => IncidentType::Fire->value,
            'description' => 'A house fire was spotted near the barangay hall spreading quickly.',
            'latitude' => 18.2756,
            'longitude' => 121.6756,
            'priority' => Priority::High->value,
            'emergency_contact' => '09179998888',
        ])
        ->assertRedirect(route('my-reports'));

    Queue::assertPushed(SendSms::class, 1);
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->phone === '09179998888'
        && str_contains($job->message, 'Your report was received')
        && str_contains($job->message, 'and is now verified')
        && str_contains($job->message, 'Fire'));
});

test('no SMS is sent when the incident has no contact number', function () {
    Queue::fake([SendSms::class]);

    $user = User::factory()->communityUser()->create(['contact_number' => null]);

    $this->actingAs($user)
        ->post(route('report.store'), [
            'incident_type' => IncidentType::Fire->value,
            'description' => 'A house fire was spotted near the barangay hall spreading quickly.',
            'latitude' => 18.2756,
            'longitude' => 121.6756,
            'priority' => Priority::High->value,
        ])
        ->assertRedirect(route('my-reports'));

    Queue::assertNothingPushed();
});

test('a successfully sent SMS is recorded in the incident status history', function () {
    withSemaphoreDriver();
    $this->mock(SemaphoreClient::class)
        ->shouldReceive('send')
        ->andReturn(json_encode([['message_id' => 1, 'status' => 'Queued']]));

    $user = User::factory()->communityUser()->create();
    $incident = Incident::factory()->create(['user_id' => $user->id, 'emergency_contact' => '09179998888']);

    (new SendSms('09179998888', 'ResQHub: Test message.', $incident->id))
        ->handle(app(SmsService::class));

    $log = $incident->statusLogs()->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($user->id);
    expect($log->old_status)->toBe($incident->status->value);
    expect($log->new_status)->toBe($incident->status->value);
    expect($log->note)->toContain('09179998888');
    expect($log->note)->toContain('ResQHub: Test message.');
});

test('a failed SMS is not recorded in the incident status history', function () {
    withSemaphoreDriver();
    $this->mock(SemaphoreClient::class)
        ->shouldReceive('send')
        ->andReturn(json_encode(['error' => 'Invalid sender name.']));

    $user = User::factory()->communityUser()->create();
    $incident = Incident::factory()->create(['user_id' => $user->id, 'emergency_contact' => '09179998888']);

    (new SendSms('09179998888', 'ResQHub: Test message.', $incident->id))
        ->handle(app(SmsService::class));

    expect($incident->statusLogs()->count())->toBe(0);
});
