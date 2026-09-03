<?php

namespace App\Enums;

enum IncidentSource: string
{
    case Online = 'online';
    case CallerBased = 'caller_based';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::CallerBased => 'Caller-Based',
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
