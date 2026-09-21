<?php

use App\Enums\IncidentType;
use App\Enums\Priority;
use App\Jobs\AnalyzeImageAi;
use App\Models\Evidence;
use App\Models\Incident;
use App\Models\User;
use App\Services\AiImageDetectionService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

test('submitting a report with an image stores evidence and queues AI analysis', function () {
    Queue::fake([AnalyzeImageAi::class]);
    Storage::fake('public');

    $user = User::factory()->communityUser()->create();

    $this->actingAs($user)
        ->post(route('report.store'), [
            'incident_type' => IncidentType::Fire->value,
            'description' => 'A suspicious fire at the market needs verification. Smoke was visible for hours.',
            'latitude' => 18.28,
            'longitude' => 121.68,
            'priority' => Priority::High->value,
            'evidence' => UploadedFile::fake()->create('scene.png', 100, 'image/png'),
        ])
        ->assertRedirect(route('my-reports'));

    $incident = $user->incidents()->first();
    $evidence = $incident->evidence()->first();

    expect($incident)->not->toBeNull();
    expect($evidence->file_type)->toBe('image/png');
    expect($evidence->file_size)->toBeGreaterThan(0);

    Storage::disk('public')->assertExists($evidence->file_path);

    Queue::assertPushed(AnalyzeImageAi::class, fn (AnalyzeImageAi $job) => $job->evidence->is($evidence));
});

test('an incident can be submitted without an image', function () {
    Queue::fake([AnalyzeImageAi::class]);

    $user = User::factory()->communityUser()->create();

    $this->actingAs($user)
        ->post(route('report.store'), [
            'incident_type' => IncidentType::Fire->value,
            'description' => 'A suspicious fire at the market needs verification. Smoke was visible for hours.',
            'latitude' => 18.28,
            'longitude' => 121.68,
            'priority' => Priority::High->value,
        ])
        ->assertRedirect(route('my-reports'));

    expect(Evidence::count())->toBe(0);

    Queue::assertNothingPushed();
});

test('the analysis job records an AI-generated result', function () {
    Http::preventStrayRequests();
    Http::fake([
        'router.huggingface.co/*' => Http::response(['label' => 'ai_generated', 'score' => 0.98], 200),
    ]);
    Storage::fake('public');

    $incident = Incident::factory()->underVerification()->create();
    Storage::disk('public')->put('evidence/photo.png', 'image-bytes');

    $evidence = Evidence::create([
        'incident_id' => $incident->id,
        'file_path' => 'evidence/photo.png',
        'file_type' => 'image/png',
        'original_name' => 'photo.png',
        'file_size' => 11,
        'uploaded_at' => now(),
    ]);

    (new AnalyzeImageAi($evidence))->handle(app(AiImageDetectionService::class));

    $evidence->refresh();

    expect($evidence->ai_is_generated)->toBeTrue();
    expect($evidence->ai_label)->toBe('ai_generated');
    expect($evidence->ai_score)->toBe(0.98);
    expect($evidence->ai_analyzed_at)->not->toBeNull();
    expect($evidence->ai_error)->toBeNull();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'dima806/ai_vs_human_generated_image_detection'));
});

test('the analysis job records a human-generated result', function () {
    Http::preventStrayRequests();
    Http::fake([
        'router.huggingface.co/*' => Http::response(['label' => 'human_generated', 'score' => 0.91], 200),
    ]);
    Storage::fake('public');

    $incident = Incident::factory()->underVerification()->create();
    Storage::disk('public')->put('evidence/photo.png', 'image-bytes');

    $evidence = Evidence::create([
        'incident_id' => $incident->id,
        'file_path' => 'evidence/photo.png',
        'file_type' => 'image/png',
        'original_name' => 'photo.png',
        'file_size' => 11,
        'uploaded_at' => now(),
    ]);

    (new AnalyzeImageAi($evidence))->handle(app(AiImageDetectionService::class));

    $evidence->refresh();

    expect($evidence->ai_is_generated)->toBeFalse();
    expect($evidence->ai_error)->toBeNull();
});

test('the analysis job retries transient failures before marking the evidence as failed', function () {
    Http::preventStrayRequests();
    Http::fake([
        'router.huggingface.co/*' => Http::response('Upstream model error', 503),
    ]);
    Storage::fake('public');
    Sleep::fake();

    $incident = Incident::factory()->underVerification()->create();
    Storage::disk('public')->put('evidence/photo.png', 'image-bytes');

    $evidence = Evidence::create([
        'incident_id' => $incident->id,
        'file_path' => 'evidence/photo.png',
        'file_type' => 'image/png',
        'original_name' => 'photo.png',
        'file_size' => 11,
        'uploaded_at' => now(),
    ]);

    (new AnalyzeImageAi($evidence))->handle(app(AiImageDetectionService::class));

    $evidence->refresh();

    expect($evidence->ai_is_generated)->toBeNull();
    expect($evidence->ai_error)->not->toBeNull();
    expect($evidence->ai_error)->toContain('503');

    Http::assertSentCount(3);
    Sleep::assertSlept(fn ($duration) => $duration->totalSeconds === 5.0, 2);
});

test('the analysis job succeeds when a transient error clears on retry', function () {
    Http::preventStrayRequests();
    Http::fake([
        'router.huggingface.co/*' => Http::sequence()
            ->push('Model is loading', 503)
            ->push(['label' => 'ai_generated', 'score' => 0.97], 200)
            ->whenEmpty(Http::response([], 500)),
    ]);
    Storage::fake('public');
    Sleep::fake();

    $incident = Incident::factory()->underVerification()->create();
    Storage::disk('public')->put('evidence/photo.png', 'image-bytes');

    $evidence = Evidence::create([
        'incident_id' => $incident->id,
        'file_path' => 'evidence/photo.png',
        'file_type' => 'image/png',
        'original_name' => 'photo.png',
        'file_size' => 11,
        'uploaded_at' => now(),
    ]);

    (new AnalyzeImageAi($evidence))->handle(app(AiImageDetectionService::class));

    $evidence->refresh();

    expect($evidence->ai_is_generated)->toBeTrue();
    expect($evidence->ai_score)->toBe(0.97);
    expect($evidence->ai_error)->toBeNull();

    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration) => $duration->totalSeconds === 5.0, 1);
});

test('the analysis job fails fast on authorization errors', function () {
    Http::preventStrayRequests();
    Http::fake([
        'router.huggingface.co/*' => Http::response('Invalid or missing token', 401),
    ]);
    Storage::fake('public');
    Sleep::fake();

    $incident = Incident::factory()->underVerification()->create();
    Storage::disk('public')->put('evidence/photo.png', 'image-bytes');

    $evidence = Evidence::create([
        'incident_id' => $incident->id,
        'file_path' => 'evidence/photo.png',
        'file_type' => 'image/png',
        'original_name' => 'photo.png',
        'file_size' => 11,
        'uploaded_at' => now(),
    ]);

    (new AnalyzeImageAi($evidence))->handle(app(AiImageDetectionService::class));

    $evidence->refresh();

    expect($evidence->ai_error)->not->toBeNull();
    expect($evidence->ai_error)->toContain('401');

    Http::assertSentCount(1);
    Sleep::assertSlept(fn () => true, 0);
});

test('the dashboard incident page shows the AI verdict for analyzed evidence', function () {
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
