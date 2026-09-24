<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\StatusLog;
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
        if (! $sms->send($this->phone, $this->message)) {
            return;
        }

        $incident = $this->incidentId === null ? null : Incident::find($this->incidentId);

        if ($incident === null) {
            return;
        }

        $status = $incident->status->value;

        StatusLog::create([
            'incident_id' => $incident->id,
            'user_id' => $incident->user_id,
            'old_status' => $status,
            'new_status' => $status,
            'note' => 'SMS sent to '.$this->phone.': '.$this->message,
            'created_at' => now(),
        ]);
    }
}
