<?php

namespace App\Services;

use App\Models\SmsMessage;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Semaphore\SemaphoreClient;

class SmsService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const MAX_ATTEMPTS = 2;

    public function __construct(private readonly SemaphoreClient $semaphore) {}

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
        $body = $this->semaphore->send($phone, $message);

        $payload = json_decode((string) $body, true);

        if (is_array($payload) && isset($payload['error'])) {
            throw new RuntimeException('Semaphore SMS failed: '.$payload['error']);
        }
    }
}
