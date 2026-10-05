<?php

use App\Enums\IncidentType;
use App\Http\Resources\EvidenceResource;
use App\Jobs\SendSms;
use App\Models\Evidence;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function evidenceReportPayload(array $overrides = []): array
{
    return array_merge([
        'incident_type' => IncidentType::Fire->value,
        'description' => 'A suspicious fire at the market needs verification. Smoke was visible for hours.',
        'latitude' => 18.28,
        'longitude' => 121.68,
    ], $overrides);
}

function storeEvidenceBytes(string $contents): Evidence
{
    return Evidence::factory()
        ->withFile($contents)
        ->create();
}

test('submitting a report keeps the image in the database instead of on disk', function () {
    Queue::fake([SendSms::class]);
    Storage::fake('public');
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response([['label' => 'ai_generated', 'score' => 0.98]], 200)]);

    $user = User::factory()->communityUser()->create();
    $image = UploadedFile::fake()->create('scene.png', 100, 'image/png');

    $this->actingAs($user)
        ->post(route('report.store'), evidenceReportPayload(['evidence' => $image]))
        ->assertRedirect(route('my-reports'));

    $evidence = Evidence::firstOrFail();

    expect($evidence->file_path)->toStartWith('evidence/');
    expect($evidence->file->content)->toBe($image->getContent());
    expect($evidence->file_size)->toBe($image->getSize());

    expect(Storage::disk('public')->allFiles('evidence'))->toBe([]);
});

test('the image route streams the stored bytes with the evidence mime type', function () {
    $evidence = storeEvidenceBytes('raw-png-bytes');
    $evidence->update(['file_type' => 'image/png', 'original_name' => 'scene.png']);

    $response = $this->get(route('evidence.image', $evidence));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');
    $response->assertHeader('Content-Length', (string) strlen('raw-png-bytes'));
    $response->assertHeader('Content-Disposition', 'inline; filename="scene.png"');
    expect($response->getContent())->toBe('raw-png-bytes');
});

test('the image route serves downloads as an attachment', function () {
    $evidence = storeEvidenceBytes('raw-png-bytes');
    $evidence->update(['original_name' => 'scene.png']);

    $this->get(route('evidence.image', ['evidence' => $evidence, 'download' => 1]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="scene.png"');
});

test('the image route strips a quoted filename', function () {
    $evidence = storeEvidenceBytes('raw-png-bytes');
    $evidence->update(['original_name' => 'a"b.png']);

    $this->get(route('evidence.image', $evidence))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="ab.png"');
});

test('the image route sniffs a content type for evidence that stored a bare extension', function () {
    $evidence = storeEvidenceBytes('raw-png-bytes');
    $evidence->update(['file_type' => 'png']);

    $this->get(route('evidence.image', $evidence))->assertOk();
});

test('the image route is reachable without logging in', function () {
    $this->get(route('evidence.image', storeEvidenceBytes('raw-png-bytes')))->assertOk();
});

test('the image route answers 404 for evidence whose bytes were never stored', function () {
    $evidence = Evidence::factory()->create();

    $this->get(route('evidence.image', $evidence))->assertNotFound();
});

test('deleting an incident takes the stored image with it', function () {
    $incident = Incident::factory()->create();
    $evidence = Evidence::factory()->withFile('raw-png-bytes')->for($incident, 'incident')->create();
    $evidenceId = $evidence->id;

    expect(DB::table('evidence_files')->where('evidence_id', $evidenceId)->exists())->toBeTrue();

    $incident->delete();

    expect(Evidence::whereKey($evidenceId)->exists())->toBeFalse();
    expect(DB::table('evidence_files')->where('evidence_id', $evidenceId)->exists())->toBeFalse();
});

test('the backfill command copies images that are still on disk', function () {
    Storage::fake('public');
    Storage::disk('public')->put('evidence/legacy.png', 'legacy-bytes');

    $evidence = Evidence::factory()

        ->create(['file_path' => 'evidence/legacy.png']);

    $this->artisan('evidence:store-in-database')->assertSuccessful();

    expect($evidence->fresh()->file->content)->toBe('legacy-bytes');
});

test('the backfill command reports evidence it cannot find and writes nothing on a dry run', function () {
    Storage::fake('public');

    $evidence = Evidence::factory()

        ->create(['file_path' => 'evidence/gone.png']);

    $this->artisan('evidence:store-in-database --dry-run')
        ->expectsOutputToContain('Missing on disk')
        ->assertSuccessful();

    expect($evidence->fresh()->file)->toBeNull();

    $this->artisan('evidence:store-in-database')
        ->expectsOutputToContain('Missing on disk')
        ->assertSuccessful();

    expect($evidence->fresh()->file)->toBeNull();
});

test('the backfill command leaves already stored images alone', function () {
    Storage::fake('public');

    $evidence = storeEvidenceBytes('stored-bytes');

    $this->artisan('evidence:store-in-database')->assertSuccessful();

    expect($evidence->fresh()->file->content)->toBe('stored-bytes');
});

test('the API resource points at the image route', function () {
    $evidence = storeEvidenceBytes('raw-png-bytes');

    expect((new EvidenceResource($evidence))->toArray(request()))
        ->toHaveKey('url', route('evidence.image', $evidence));
});

test('the incident pages link evidence at the image route', function () {
    $incident = Incident::factory()->verified()->create();
    $evidence = storeEvidenceBytes('raw-png-bytes');
    $evidence->update(['incident_id' => $incident->id]);

    $expected = route('evidence.image', $evidence);

    $this->get(route('incidents.show', $incident))
        ->assertOk()
        ->assertSee($expected, escape: false);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard.incidents.show', $incident))
        ->assertOk()
        ->assertSee($expected, escape: false);
});
