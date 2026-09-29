<?php

namespace App\Enums;

enum IncidentType: string
{
    /** Disaster Risk */
    case Typhoon = 'typhoon';
    case Flood = 'flood';
    case Earthquake = 'earthquake';
    case Landslide = 'landslide';

    /** Incidents */
    case VehicularAccident = 'vehicular_accident';
    case Fire = 'fire';
    case Drowning = 'drowning';
    case Hazmat = 'hazmat';
    case Ems = 'ems';
    case PatientTransport = 'patient_transport';

    /** Others */
    case Others = 'others';

    /**
     * Superseded by the separate Typhoon and Flood cases. Kept so historical
     * records stay readable; it can no longer be selected or submitted.
     */
    case TyphoonFlood = 'typhoon_flood';

    public const CATEGORY_DISASTER_RISK = 'disaster_risk';

    public const CATEGORY_INCIDENTS = 'incidents';

    public const CATEGORY_OTHERS = 'others';

    /**
     * Main categories in display order.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        self::CATEGORY_DISASTER_RISK => 'Disaster Risk',
        self::CATEGORY_INCIDENTS => 'Incidents',
        self::CATEGORY_OTHERS => 'Others',
    ];

    public function label(): string
    {
        return match ($this) {
            self::Typhoon => 'Typhoon',
            self::Flood => 'Flood',
            self::Earthquake => 'Earthquake',
            self::Landslide => 'Landslide',
            self::VehicularAccident => 'Vehicular Accident',
            self::Fire => 'Fire',
            self::Drowning => 'Drowning',
            self::Hazmat => 'Hazardous Materials',
            self::Ems => 'Emergency Medical Services',
            self::PatientTransport => 'Patient Transport',
            self::Others => 'Others',
            self::TyphoonFlood => 'Typhoon / Flood',
        };
    }

    /**
     * The main category this incident type belongs to.
     */
    public function category(): string
    {
        return match ($this) {
            self::Typhoon, self::Flood, self::Earthquake, self::Landslide, self::TyphoonFlood => self::CATEGORY_DISASTER_RISK,
            self::VehicularAccident, self::Fire, self::Drowning, self::Hazmat, self::Ems, self::PatientTransport => self::CATEGORY_INCIDENTS,
            self::Others => self::CATEGORY_OTHERS,
        };
    }

    /**
     * Whether this type only exists for historical records.
     */
    public function isLegacy(): bool
    {
        return $this === self::TyphoonFlood;
    }

    /**
     * Types that may be selected for a new or edited report.
     *
     * @return array<int, IncidentType>
     */
    public static function selectableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (IncidentType $type) => ! $type->isLegacy(),
        ));
    }

    /**
     * Types that may no longer be selected, kept for historical display.
     *
     * @return array<int, IncidentType>
     */
    public static function legacyCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (IncidentType $type) => $type->isLegacy(),
        ));
    }

    /**
     * Selectable values, for validation and filter options.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::selectableCases(), 'value');
    }

    /**
     * Every value, including legacy ones, for the database column definition.
     *
     * @return array<int, string>
     */
    public static function allValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Every label keyed by value, including legacy ones.
     *
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
     * Selectable labels keyed by value, for dropdowns and filters.
     *
     * @return array<string, string>
     */
    public static function selectableLabels(): array
    {
        $labels = [];

        foreach (self::selectableCases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }

    /**
     * Selectable types grouped by main category, ready for optgroups.
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::CATEGORIES as $category => $categoryLabel) {
            $grouped[$category] = [
                'label' => $categoryLabel,
                'types' => [],
            ];

            foreach (self::selectableCases() as $case) {
                if ($case->category() === $category) {
                    $grouped[$category]['types'][$case->value] = $case->label();
                }
            }
        }

        return $grouped;
    }
}
