<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiImageDetector
{
    private const MAX_ERROR_LENGTH = 500;

    /**
     * Send raw image bytes to Hugging Face and normalize the verdict.
     *
     * Never throws and never logs: the caller receives the failure array instead.
     *
     * @return array{success: true, label: string, confidence: float, predictions: array}|array{success: false, error: string}
     */
    public function predict(string $imagePath, ?string $mimeType = null): array
    {
        $bytes = $this->readImage($imagePath);

        if ($bytes === null) {
            return ['success' => false, 'error' => 'Image file not found.'];
        }

        $token = (string) config('services.huggingface.token', '');

        $request = Http::acceptJson()
            ->timeout((int) config('services.huggingface.timeout', 60))
            ->withBody($bytes, $this->resolveMimeType($imagePath, $mimeType));

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post((string) config('services.huggingface.url'));
        } catch (ConnectionException $exception) {
            return $this->connectionFailure($exception->getMessage());
        } catch (Throwable $exception) {
            return $this->upstreamFailure($exception->getMessage());
        }

        $payload = $response->json();

        if ($payload === null) {
            return ['success' => false, 'error' => "Hugging Face returned non-JSON (HTTP {$response->status()})."];
        }

        if (! $response->successful()) {
            $message = is_array($payload) ? ($payload['error'] ?? $response->body()) : $response->body();

            return [
                'success' => false,
                'error' => "Hugging Face error (HTTP {$response->status()}): ".substr((string) $message, 0, self::MAX_ERROR_LENGTH),
            ];
        }

        $predictions = is_array($payload) && array_is_list($payload)
            ? $payload
            : (is_array($payload) ? ($payload['predictions'] ?? null) : null);

        if (! is_array($predictions) || $predictions === []) {
            return ['success' => false, 'error' => 'Hugging Face returned no predictions.'];
        }

        $top = $predictions[0];

        return [
            'success' => true,
            'label' => (string) ($top['label'] ?? 'unknown'),
            'confidence' => round((float) ($top['score'] ?? 0), 4),
            'predictions' => $predictions,
        ];
    }

    private function readImage(string $imagePath): ?string
    {
        if ($imagePath === '' || ! is_file($imagePath) || ! is_readable($imagePath)) {
            return null;
        }

        $bytes = @file_get_contents($imagePath);

        return $bytes === false ? null : $bytes;
    }

    /**
     * Evidence stores either a real mime type or, when detection failed, a bare
     * extension such as "png", which Hugging Face cannot accept as a Content-Type.
     */
    private function resolveMimeType(string $imagePath, ?string $mimeType): string
    {
        if ($mimeType !== null && $mimeType !== '' && str_contains($mimeType, '/')) {
            return $mimeType;
        }

        return mime_content_type($imagePath) ?: 'application/octet-stream';
    }

    /**
     * @return array{success: false, error: string}
     */
    private function connectionFailure(string $message): array
    {
        $haystack = strtolower($message);

        if (str_contains($haystack, 'timed out') || str_contains($haystack, 'timeout') || str_contains($haystack, 'curl error 28')) {
            return ['success' => false, 'error' => 'AI detection timed out.'];
        }

        return $this->upstreamFailure($message);
    }

    /**
     * @return array{success: false, error: string}
     */
    private function upstreamFailure(string $message): array
    {
        return [
            'success' => false,
            'error' => 'Upstream inference failed: '.substr($message, 0, self::MAX_ERROR_LENGTH),
        ];
    }
}
