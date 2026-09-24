<?php

namespace App\Jobs;

use App\Models\Incident;
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
        public ?int $incidentId = null,
    ) {}

    public function handle(SmsService $sms): void
    {
        if ($this->incidentId === null) {
            $sms->send($this->phone, $this->message);

            return;
        }

        $incident = Incident::find($this->incidentId);

        if ($incident === null) {
            $sms->send($this->phone, $this->message);

            return;
        }

        $sms->sendForIncident($this->phone, $this->message, $incident);
    }
}
