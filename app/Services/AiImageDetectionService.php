<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiImageDetectionService
{
    private const HUMAN_LABELS = ['human', 'real', 'original', 'photo', 'not_ai'];

    private const AI_LABELS = ['ai', 'fake', 'generated', 'synthetic', 'digital'];

    private readonly string $url;

    private readonly string $token;

    private readonly int $timeout;

    public function __construct(?string $url = null, ?string $token = null, ?int $timeout = null)
    {
        $this->url = rtrim($url ?? (string) config('services.ai_detection.url', ''), '/');
        $this->token = $token ?? (string) config('services.ai_detection.token', '');
        $this->timeout = $timeout ?? (int) config('services.ai_detection.timeout', 60);
    }

    /**
     * Send an image to the AI detection server and return a normalized prediction.
     *
     * @return array{label: string, is_ai_generated: bool, score: float}
     *
     * @throws RuntimeException when the image could not be analyzed, with a user-friendly message
     */
    public function detect(string $imagePath): array
    {
        $contents = @file_get_contents($imagePath);

        if ($contents === false) {
            throw new RuntimeException('AI detection failed.');
        }

        $response = $this->callInference($contents, basename($imagePath));

        if ($response->successful()) {
            return $this->normalize($response->json() ?? []);
        }

        Log::error('AI detection returned an unsuccessful status', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new RuntimeException("AI detection failed (HTTP {$response->status()}).");
    }

    /**
     * Normalize the AI server prediction into the stored shape.
     *
     * The server is expected to return JSON like:
     * {"success": true, "label": "ai_generated", "confidence": 0.98}
     *
     * @return array{label: string, is_ai_generated: bool, score: float}
     *
     * @throws RuntimeException when the response cannot be interpreted
     */
    public function normalize(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new RuntimeException('Invalid AI detection response.');
        }

        $payload = $payload['data'] ?? $payload;

        if (array_key_exists('success', $payload) && $payload['success'] === false) {
            throw new RuntimeException('AI detection failed.');
        }

        $entry = is_array($payload) && array_key_exists('label', $payload) ? $payload : ($payload[0] ?? []);

        $label = is_string($entry['label'] ?? null) ? $entry['label'] : '';

        if ($label === '') {
            throw new RuntimeException('Invalid AI detection response.');
        }

        return [
            'label' => $label,
            'is_ai_generated' => $this->labelIsAi($label),
            'score' => (float) ($entry['confidence'] ?? $entry['score'] ?? 0),
        ];
    }

    private function callInference(string $contents, string $filename): Response
    {
        $request = Http::timeout($this->timeout);

        if ($this->token !== '') {
            $request = $request->withToken($this->token);
        }

        try {
            return $request
                ->attach('file', $contents, $filename)
                ->post($this->url.'/predict');
        } catch (ConnectionException $exception) {
            Log::error('AI detection request failed', [
                'exception' => $exception->getMessage(),
            ]);

            throw new RuntimeException($this->connectionFailureMessage($exception->getMessage()));
        }
    }

    private function connectionFailureMessage(string $message): string
    {
        $haystack = strtolower($message);

        if (str_contains($haystack, 'timed out') || str_contains($haystack, 'timeout') || str_contains($haystack, 'curl error 28')) {
            return 'AI detection timed out.';
        }

        return 'AI service unavailable.';
    }

    /**
     * @throws RuntimeException when the label cannot be classified
     */
    private function labelIsAi(string $label): bool
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

        throw new RuntimeException('Invalid AI detection response.');
    }
}
