<?php

use App\Support\AiImageDetector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const HF_ROUTER_URL = 'http://hf.internal/models/ai-vs-human';

function configureHuggingFace(string $token = ''): void
{
    config([
        'services.huggingface.url' => HF_ROUTER_URL,
        'services.huggingface.token' => $token,
        'services.huggingface.timeout' => 60,
    ]);
}

/**
 * Store bytes on the faked public disk and return the real path, mirroring how
 * IncidentService hands the stored evidence file to the detector.
 */
function storeFakeImage(string $contents = 'fake-image-bytes'): string
{
    Storage::fake('public');

    Storage::disk('public')->put('evidence/scene.png', $contents);

    return Storage::disk('public')->path('evidence/scene.png');
}

beforeEach(function () {
    configureHuggingFace();
});

test('it returns a normalized verdict for a bare list payload', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*' => Http::response([
            ['label' => 'ai_generated', 'score' => 0.9876543],
            ['label' => 'human_generated', 'score' => 0.0123456],
        ], 200),
    ]);

    expect(app(AiImageDetector::class)->predict(storeFakeImage(), 'image/png'))->toBe([
        'success' => true,
        'label' => 'ai_generated',
        'confidence' => 0.9877,
        'predictions' => [
            ['label' => 'ai_generated', 'score' => 0.9876543],
            ['label' => 'human_generated', 'score' => 0.0123456],
        ],
    ]);
});

test('it reads predictions from a predictions key and sends the raw bytes with the token', function () {
    configureHuggingFace('hf_fake_token');
    Http::preventStrayRequests();
    Http::fake([
        '*' => Http::response([
            'predictions' => [
                ['label' => 'human_generated', 'score' => 0.91],
            ],
        ], 200),
    ]);

    expect(app(AiImageDetector::class)->predict(storeFakeImage('raw-bytes-here'), 'image/png'))->toBe([
        'success' => true,
        'label' => 'human_generated',
        'confidence' => 0.91,
        'predictions' => [
            ['label' => 'human_generated', 'score' => 0.91],
        ],
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === HF_ROUTER_URL
        && $request->method() === 'POST'
        && $request->body() === 'raw-bytes-here'
        && $request->hasHeader('Authorization', 'Bearer hf_fake_token')
        && $request->hasHeader('Accept', 'application/json')
        && $request->hasHeader('Content-Type', 'image/png'));
});

test('it omits the authorization header when no token is configured', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response([['label' => 'ai', 'score' => 1]], 200)]);

    app(AiImageDetector::class)->predict(storeFakeImage(), 'image/png');

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization'));
});

test('it reports a non-JSON upstream response', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('not a json body', 200)]);

    expect(app(AiImageDetector::class)->predict(storeFakeImage(), 'image/png'))->toBe([
        'success' => false,
        'error' => 'Hugging Face returned non-JSON (HTTP 200).',
    ]);
});

test('it reports the status and truncated error for an unsuccessful upstream response', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['error' => 'Model is currently loading'], 503)]);

    expect(app(AiImageDetector::class)->predict(storeFakeImage(), 'image/png'))->toBe([
        'success' => false,
        'error' => 'Hugging Face error (HTTP 503): Model is currently loading',
    ]);
});

test('it reports an empty predictions list', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['predictions' => []], 200)]);

    expect(app(AiImageDetector::class)->predict(storeFakeImage(), 'image/png'))->toBe([
        'success' => false,
        'error' => 'Hugging Face returned no predictions.',
    ]);
});

test('it reports a timeout when the connection times out', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received'),
    ]);

    expect(app(AiImageDetector::class)->predict(storeFakeImage(), 'image/png'))->toBe([
        'success' => false,
        'error' => 'AI detection timed out.',
    ]);
});

test('it reports an upstream failure for other connection errors', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*' => fn () => throw new ConnectionException('cURL error 7: Failed to connect to hf.internal port 80: Connection refused'),
    ]);

    expect(app(AiImageDetector::class)->predict(storeFakeImage(), 'image/png'))->toBe([
        'success' => false,
        'error' => 'Upstream inference failed: cURL error 7: Failed to connect to hf.internal port 80: Connection refused',
    ]);
});

test('it reports a missing image file without contacting Hugging Face', function () {
    Http::preventStrayRequests();

    expect(app(AiImageDetector::class)->predict('/tmp/ceiris-does-not-exist.png', 'image/png'))->toBe([
        'success' => false,
        'error' => 'Image file not found.',
    ]);

    Http::assertNothingSent();
});
