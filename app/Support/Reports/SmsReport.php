<?php

namespace App\Support\Reports;

use App\Models\SmsMessage;
use App\Models\User;
use App\Support\Reports\Concerns\AppliesReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * The SMS delivery log. Phone numbers and message bodies are personal data, so
 * this export is gated behind re-authentication like the other sensitive ones.
 */
class SmsReport implements Report
{
    use AppliesReportFilters;

    /**
     * @return array<int, string>
     */
    public static function filters(): array
    {
        return ['from', 'to', 'status'];
    }

    public function title(): string
    {
        return 'SMS Delivery Report';
    }

    public function filenamePrefix(): string
    {
        return 'resqhub-sms';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Phone',
            'Status',
            'Attempts',
            'Sent At',
            'Message',
            'Error',
            'Created At',
        ];
    }

    public function emptyMessage(): string
    {
        return 'No SMS messages found in this period.';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Generator<int, array<int, string|null>>
     */
    public function rows(array $filters, ?User $viewer = null, ?int $limit = null, string $dateFormat = 'Y-m-d H:i:s'): \Generator
    {
        $query = $this->baseQuery($filters, $limit);

        foreach ($query->cursor() as $message) {
            yield [
                (string) $message->id,
                $this->formatText($message->phone),
                $this->formatText($message->status),
                (string) $message->attempts,
                $this->formatDate($message->sent_at, $dateFormat),
                $this->formatText($message->message, 140),
                $this->formatText($message->error, 80),
                $this->formatDate($message->created_at, $dateFormat),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(array $filters, ?int $limit = null): Builder
    {
        $query = SmsMessage::query()->orderByDesc('created_at');

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $this->withinPeriod($query, $filters, 'created_at', $limit);
    }
}
