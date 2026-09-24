<?php

use App\Enums\IncidentType;
use App\Enums\Priority;
use App\Jobs\SendSms;
use App\Models\Incident;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function withSemaphoreDriver(?string $sender = 'RESQHUB'): void
{
    config([
        'services.sms.driver' => 'semaphore',
        'services.sms.semaphore' => [
            'url' => 'https://api.semaphore.co/api/v4/messages',
            'key' => 'secret-key',
            'sender' => $sender,
            'timeout' => 15,
        ],
    ]);
}

test('the semaphore driver posts the message and marks it sent', function () {
    withSemaphoreDriver();
    Http::preventStrayRequests();
    Http::fake([
        'api.semaphore.co/*' => Http::response([
            ['message_id' => 123, 'number' => '09171234567', 'status' => 'Queued'],
        ], 200),
    ]);

    $sent = (new SmsService)->send('09171234567', 'Hello');

    expect($sent)->toBeTrue();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.semaphore.co/api/v4/messages'
            && $request->method() === 'POST'
            && $request['apikey'] === 'secret-key'
            && $request['number'] === '09171234567'
            && $request['message'] === 'Hello'
            && $request['sendername'] === 'RESQHUB';
    });

    $log = SmsMessage::first();

    expect($log)->not->toBeNull();
    expect($log->phone)->toBe('09171234567');
    expect($log->status)->toBe('sent');
    expect($log->attempts)->toBe(1);
    expect($log->sent_at)->not->toBeNull();
});

test('the semaphore driver omits the sender name when none is configured', function () {
    withSemaphoreDriver(null);
    Http::preventStrayRequests();
    Http::fake([
        'api.semaphore.co/*' => Http::response([
            ['message_id' => 456, 'number' => '09171234567', 'status' => 'Queued'],
        ], 200),
    ]);

    (new SmsService)->send('09171234567', 'Hello');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.semaphore.co/api/v4/messages'
        && ! isset($request['sendername']));
});

test('the semaphore driver records a failure when the API rejects the message', function () {
    withSemaphoreDriver();
    Http::preventStrayRequests();
    Http::fake([
        'api.semaphore.co/*' => Http::response('Unauthorized', 401),
    ]);

    $sent = (new SmsService)->send('09171234567', 'Hello');

    expect($sent)->toBeFalse();

    $log = SmsMessage::first();

    expect($log->status)->toBe('failed');
    expect($log->attempts)->toBe(2);
    expect($log->sent_at)->toBeNull();
});

test('an unsupported SMS driver records a failure and does not throw', function () {
    config(['services.sms.driver' => 'twilio']);

    $sent = (new SmsService)->send('09171234567', 'Hello');

    expect($sent)->toBeFalse();
    expect(SmsMessage::first()->status)->toBe('failed');
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
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->phone === '09179998888');
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
            'caller_contact' => '09179876543',
        ])
        ->assertRedirect(route('dashboard.incidents.show', Incident::first()));

    Queue::assertPushed(SendSms::class, 1);
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->phone === '09179876543');
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
