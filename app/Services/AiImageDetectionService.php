<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class AiImageDetectionService
{
    private const HUMAN_LABELS = ['human', 'real', 'original', 'photo', 'not_ai'];

    private const AI_LABELS = ['ai', 'fake', 'generated', 'synthetic', 'digital'];

    private const MAX_ATTEMPTS = 3;

    private const RETRY_AFTER_SECONDS = 5;

    private const TRANSIENT_STATUSES = [408, 429, 500, 502, 503, 504];

    private readonly string $token;

    private readonly string $detectionUrl;

    public function __construct(?string $token = null, ?string $detectionUrl = null)
    {
        $this->token = $token ?? (string) config('services.huggingface.token', '');
        $this->detectionUrl = $detectionUrl ?? (string) config('services.huggingface.detection_url', '');
    }

    /**
     * Send image bytes to the Hugging Face inference API and return a detection result.
     *
     * Transient failures (429, 5xx, network hiccups) are retried briefly because a
     * cold-start model can return 503 while it loads.
     *
     * @return array{label: string, is_ai_generated: bool, score: float}
     *
     * @throws RuntimeException when the image was not analyzed successfully
     */
    public function analyze(string $imageBytes, string $contentType): array
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = $this->callInference($imageBytes, $contentType);
            } catch (ConnectionException $exception) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw new RuntimeException('Hugging Face inference is unreachable: '.$exception->getMessage(), previous: $exception);
                }

                Sleep::sleep(self::RETRY_AFTER_SECONDS);

                continue;
            }

            if ($response->successful()) {
                return $this->normalize($response->json() ?? []);
            }

            if (! in_array($response->status(), self::TRANSIENT_STATUSES, true) || $attempt === self::MAX_ATTEMPTS) {
                throw new RuntimeException("Hugging Face inference failed with status {$response->status()}.".($response->body() === '' ? '' : ' '.substr($response->body(), 0, 200)));
            }

            Sleep::sleep(self::RETRY_AFTER_SECONDS);
        }

        throw new RuntimeException('Hugging Face inference did not succeed after '.self::MAX_ATTEMPTS.' attempts.');
    }

    /**
     * @return array{label: string, is_ai_generated: bool, score: float}
     *
     * @throws RuntimeException when no matching label is found
     */
    public function normalize(mixed $payload): array
    {
        $entry = is_array($payload) && array_key_exists('label', $payload) ? $payload : (is_array($payload) ? $payload[0] ?? [] : []);

        $label = is_string($entry['label'] ?? null) ? $entry['label'] : '';

        if ($label === '') {
            throw new RuntimeException('Hugging Face inference returned no label.');
        }

        $haystack = strtolower($label);

        foreach (self::HUMAN_LABELS as $humanLabel) {
            if (str_contains($haystack, $humanLabel)) {
                return $this->result($label, false, $this->score($entry));
            }
        }

        foreach (self::AI_LABELS as $aiLabel) {
            if (str_contains($haystack, $aiLabel)) {
                return $this->result($label, true, $this->score($entry));
            }
        }

        throw new RuntimeException("Hugging Face inference returned an unknown label [{$label}].");
    }

    private function callInference(string $imageBytes, string $contentType): Response
    {
        return Http::withToken($this->token)
            ->timeout(60)
            ->withBody($imageBytes, $contentType)
            ->post($this->detectionUrl);
    }

    private function score(array $entry): float
    {
        return (float) ($entry['score'] ?? 0);
    }

    /**
     * @return array{label: string, is_ai_generated: bool, score: float}
     */
    private function result(string $label, bool $isAiGenerated, float $score): array
    {
        return [
            'label' => $label,
            'is_ai_generated' => $isAiGenerated,
            'score' => $score,
        ];
    }
}
