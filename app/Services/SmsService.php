<?php

namespace App\Services;

use App\Models\SmsMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const MAX_ATTEMPTS = 2;

    /**
     * Deliver an SMS message, logging each attempt. Retries once on failure,
     * then records the failure for the in-app notification center fallback.
     */
    public function send(string $phone, string $message): bool
    {
        if (blank($phone)) {
            return false;
        }

        $log = SmsMessage::create([
            'phone' => $phone,
            'message' => $message,
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'created_at' => now(),
        ]);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $this->deliver($phone, $message);

                $log->update([
                    'status' => self::STATUS_SENT,
                    'attempts' => $attempt,
                    'sent_at' => now(),
                    'error' => null,
                ]);

                return true;
            } catch (\Throwable $exception) {
                Log::warning("SMS delivery attempt {$attempt} failed for {$phone}: {$exception->getMessage()}");
            }
        }

        $log->update([
            'status' => self::STATUS_FAILED,
            'attempts' => self::MAX_ATTEMPTS,
            'error' => 'All delivery attempts failed.',
        ]);

        Log::error("SMS delivery failed permanently for {$phone}.");

        return false;
    }

    private function deliver(string $phone, string $message): void
    {
        $driver = config('services.sms.driver', 'log');

        match ($driver) {
            'log' => Log::info("SMS to {$phone}: {$message}"),
            'semaphore' => $this->deliverViaSemaphore($phone, $message),
            default => throw new RuntimeException("Unsupported SMS driver [{$driver}]."),
        };
    }

    private function deliverViaSemaphore(string $phone, string $message): void
    {
        $config = config('services.sms.semaphore', []);

        $payload = [
            'apikey' => (string) ($config['key'] ?? ''),
            'number' => $phone,
            'message' => $message,
        ];

        if (! blank($config['sender'] ?? null)) {
            $payload['sendername'] = $config['sender'];
        }

        $response = Http::timeout((int) ($config['timeout'] ?? 15))
            ->asForm()
            ->post((string) ($config['url'] ?? 'https://api.semaphore.co/api/v4/messages'), $payload);

        if ($response->failed()) {
            throw new RuntimeException("Semaphore SMS failed (HTTP {$response->status()}): {$response->body()}");
        }
    }
}
