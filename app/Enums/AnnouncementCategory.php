<?php

namespace App\Enums;

enum AnnouncementCategory: string
{
    case Advisory = 'advisory';
    case Warning = 'warning';
    case SafetyInfo = 'safety_info';
    case IncidentUpdate = 'incident_update';

    public function label(): string
    {
        return match ($this) {
            self::Advisory => 'Advisory',
            self::Warning => 'Warning',
            self::SafetyInfo => 'Safety Information',
            self::IncidentUpdate => 'Incident Update',
        };
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
