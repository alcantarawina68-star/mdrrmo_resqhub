<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case UnderVerification = 'under_verification';
    case Verified = 'verified';
    case Ongoing = 'ongoing';
    case Closed = 'closed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::UnderVerification => 'Under Verification',
            self::Verified => 'Verified',
            self::Ongoing => 'Ongoing',
            self::Closed => 'Closed',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Statuses that are visible on the public map.
     *
     * @return array<int, IncidentStatus>
     */
    public static function publiclyVisible(): array
    {
        return [self::Verified, self::Ongoing, self::Closed];
    }

    /**
     * Values of statuses that are visible on the public map.
     *
     * @return array<int, string>
     */
    public static function publiclyVisibleValues(): array
    {
        return array_column(self::publiclyVisible(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
