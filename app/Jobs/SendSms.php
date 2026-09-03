<?php

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public string $phone,
        public string $message,
    ) {}

    public function handle(SmsService $sms): void
    {
        $sms->send($this->phone, $this->message);
    }
}
