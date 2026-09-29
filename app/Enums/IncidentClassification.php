<?php

namespace App\Enums;

enum IncidentClassification: string
{
    case Red = 'red';
    case Green = 'green';
    case Yellow = 'yellow';
    case Black = 'black';

    /**
     * The signal colour shown before the label.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::Red => '🔴',
            self::Green => '🟢',
            self::Yellow => '🟡',
            self::Black => '⚫',
        };
    }

    public function label(): string
    {
        return $this->emoji().' '.$this->name;
    }

    /**
     * Plain label without the signal colour, for exports and screen-reader text.
     */
    public function name(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Hex value for PDF and CSV exports.
     */
    public function color(): string
    {
        return match ($this) {
            self::Red => '#dc2626',
            self::Green => '#16a34a',
            self::Yellow => '#ca8a04',
            self::Black => '#171717',
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
