<?php

namespace App\Enums;

enum Severity: string
{
    case Info = 'info';
    case Caution = 'caution';
    case Urgent = 'urgent';

    public function label(): string
    {
        return ucfirst($this->value);
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
