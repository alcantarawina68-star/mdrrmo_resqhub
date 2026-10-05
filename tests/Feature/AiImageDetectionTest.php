<?php

use App\Enums\IncidentType;
use App\Jobs\SendSms;
use App\Models\Evidence;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

const AI_DETECTION_URL = 'https://router.huggingface.co/hf-inference/models/ai-vs-human';

function configureAiDetection(): void
{
    config([
        'services.huggingface.url' => AI_DETECTION_URL,
        'services.huggingface.token' => '',
        'services.huggingface.timeout' => 60,
    ]);
}

function reportPayload(array $overrides = []): array
{
    return array_merge([
        'incident_type' => IncidentType::Fire->value,
        'description' => 'A suspicious fire at the market needs verification. Smoke was visible for hours.',
        'latitude' => 18.28,
        'longitude' => 121.68,
    ], $overrides);
}

test('submitting a report with an image runs detection synchronously and saves the prediction', function () {
    configureAiDetection();
    Queue::fake([SendSms::class]);
    Storage::fake('public');
    Http::preventStrayRequests();
    Http::fake([
        AI_DETECTION_URL => Http::response([['label' => 'ai_generated', 'score' => 0.98]], 200),
    ]);

    $user = User::factory()->communityUser()->create();
    $image = UploadedFile::fake()->create('scene.png', 100, 'image/png');

    $this->actingAs($user)
        ->post(route('report.store'), reportPayload(['evidence' => $image]))
        ->assertRedirect(route('my-reports'));

    $evidence = Evidence::first();

    expect($evidence)->not->toBeNull();
    expect($evidence->ai_label)->toBe('ai_generated');
    expect($evidence->ai_is_generated)->toBeTrue();
    expect($evidence->ai_score)->toBe(0.98);
    expect($evidence->ai_analyzed_at)->not->toBeNull();
    expect($evidence->ai_error)->toBeNull();

    Http::assertSent(function (Request $request) use ($image) {
        return $request->url() === AI_DETECTION_URL
            && $request->method() === 'POST'
            && $request->body() === $image->getContent();
    });
});

test('the prediction marks human-generated images as real', function () {
    configureAiDetection();
    Queue::fake([SendSms::class]);
    Storage::fake('public');
    Http::preventStrayRequests();
    Http::fake([
        AI_DETECTION_URL => Http::response([['label' => 'human_generated', 'score' => 0.91]], 200),
    ]);

    $user = User::factory()->communityUser()->create();

    $this->actingAs($user)
        ->post(route('report.store'), reportPayload(['evidence' => UploadedFile::fake()->create('scene.png', 100, 'image/png')]))
        ->assertRedirect(route('my-reports'));

    $evidence = Evidence::first();

    expect($evidence->ai_is_generated)->toBeFalse();
    expect($evidence->ai_score)->toBe(0.91);
    expect($evidence->ai_error)->toBeNull();
});

test('a report can be submitted without an image', function () {
    configureAiDetection();
    Queue::fake([SendSms::class]);
    Http::preventStrayRequests();

    $user = User::factory()->communityUser()->create();

    $this->actingAs($user)
        ->post(route('report.store'), reportPayload())
        ->assertRedirect(route('my-reports'));

    expect(Evidence::count())->toBe(0);
    Http::assertNothingSent();
});

test('the submission still completes when the AI service is unavailable', function () {
    configureAiDetection();
    Queue::fake([SendSms::class]);
    Storage::fake('public');
    Http::preventStrayRequests();
    Http::fake([
        AI_DETECTION_URL => fn () => throw new ConnectionException('cURL error 7: Failed to connect to hf.internal port 80 after 1000 ms: Connection refused'),
    ]);

    $user = User::factory()->communityUser()->create();

    $this->actingAs($user)
        ->post(route('report.store'), reportPayload(['evidence' => UploadedFile::fake()->create('scene.png', 100, 'image/png')]))
        ->assertRedirect(route('my-reports'));

    expect(Evidence::first()->ai_error)->toBe('Upstream inference failed: cURL error 7: Failed to connect to hf.internal port 80 after 1000 ms: Connection refused');
});

test('the submission still completes when the AI detection times out', function () {
    configureAiDetection();
    Queue::fake([SendSms::class]);
    Storage::fake('public');
    Http::preventStrayRequests();
    Http::fake([
        AI_DETECTION_URL => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received'),
    ]);

    $user = User::factory()->communityUser()->create();

    $this->actingAs($user)
        ->post(route('report.store'), reportPayload(['evidence' => UploadedFile::fake()->create('scene.png', 100, 'image/png')]))
        ->assertRedirect(route('my-reports'));

    expect(Evidence::first()->ai_error)->toContain('AI detection timed out');
});

test('a malformed AI response is handled safely', function () {
    configureAiDetection();
    Queue::fake([SendSms::class]);
    Storage::fake('public');
    Http::preventStrayRequests();
    Http::fake([
        AI_DETECTION_URL => Http::response('not a json body', 200),
    ]);

    $user = User::factory()->communityUser()->create();

    $this->actingAs($user)
        ->post(route('report.store'), reportPayload(['evidence' => UploadedFile::fake()->create('scene.png', 100, 'image/png')]))
        ->assertRedirect(route('my-reports'));

    expect(Evidence::first()->ai_error)->toBe('Hugging Face returned non-JSON (HTTP 200).');
});

test('an unsuccessful AI response is recorded as failed', function () {
    configureAiDetection();
    Queue::fake([SendSms::class]);
    Storage::fake('public');
    Http::preventStrayRequests();
    Http::fake([
        AI_DETECTION_URL => Http::response(['error' => 'Upstream model error'], 503),
    ]);

    $user = User::factory()->communityUser()->create();

    $this->actingAs($user)
        ->post(route('report.store'), reportPayload(['evidence' => UploadedFile::fake()->create('scene.png', 100, 'image/png')]))
        ->assertRedirect(route('my-reports'));

    expect(Evidence::first()->ai_error)->toBe('Hugging Face error (HTTP 503): Upstream model error');
});

test('an unauthenticated user cannot submit a report', function () {
    configureAiDetection();

    $this->post(route('report.store'), reportPayload())
        ->assertRedirect('/login');

    expect(Evidence::count())->toBe(0);
});

test('non-AI jobs still run after a report submission', function () {
    configureAiDetection();
    Queue::fake([SendSms::class]);
    Storage::fake('public');
    Http::preventStrayRequests();
    Http::fake([
        AI_DETECTION_URL => Http::response([['label' => 'ai_generated', 'score' => 0.98]], 200),
    ]);

    $user = User::factory()->communityUser()->create();

    $this->actingAs($user)
        ->post(route('report.store'), reportPayload(['evidence' => UploadedFile::fake()->create('scene.png', 100, 'image/png')]))
        ->assertRedirect(route('my-reports'));

    Queue::assertPushed(SendSms::class);
});

test('the dashboard incident page shows the AI verdict for analyzed evidence', function () {
    configureAiDetection();

    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->underVerification()->create();

    Evidence::create([
        'incident_id' => $incident->id,
        'file_path' => 'evidence/photo.png',
        'file_type' => 'image/png',
        'original_name' => 'photo.png',
        'file_size' => 11,
        'uploaded_at' => now(),
        'ai_label' => 'ai_generated',
        'ai_is_generated' => true,
        'ai_score' => 0.98,
        'ai_analyzed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard.incidents.show', $incident))
        ->assertOk()
        ->assertSee('AI-generated image')
        ->assertSee('98.0% confidence');
});

test('the dashboard incident page shows a pending state before the AI check completes', function () {
    configureAiDetection();

    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->underVerification()->create();

    Evidence::create([
        'incident_id' => $incident->id,
        'file_path' => 'evidence/photo.png',
        'file_type' => 'image/png',
        'original_name' => 'photo.png',
        'file_size' => 11,
        'uploaded_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard.incidents.show', $incident))
        ->assertOk()
        ->assertSee('AI check pending');
});

test('the public incident page shows the AI verdict for analyzed evidence', function () {
    configureAiDetection();

    $incident = Incident::factory()->verified()->create();

    Evidence::create([
        'incident_id' => $incident->id,
        'file_path' => 'evidence/photo.png',
        'file_type' => 'image/png',
        'original_name' => 'photo.png',
        'file_size' => 11,
        'uploaded_at' => now(),
        'ai_label' => 'human_generated',
        'ai_is_generated' => false,
        'ai_score' => 0.93,
        'ai_analyzed_at' => now(),
    ]);

    $this->get(route('incidents.show', $incident))
        ->assertOk()
        ->assertSee('Likely real photo')
        ->assertSee('93.0% confidence');
});
