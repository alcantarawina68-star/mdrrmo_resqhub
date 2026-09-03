<?php

namespace App\Enums;

enum IncidentType: string
{
    case TyphoonFlood = 'typhoon_flood';
    case Earthquake = 'earthquake';
    case Landslide = 'landslide';
    case VehicularAccident = 'vehicular_accident';
    case Fire = 'fire';
    case Drowning = 'drowning';
    case Hazmat = 'hazmat';
    case Ems = 'ems';
    case PatientTransport = 'patient_transport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TyphoonFlood => 'Typhoon / Flood',
            self::Earthquake => 'Earthquake',
            self::Landslide => 'Landslide',
            self::VehicularAccident => 'Vehicular Accident',
            self::Fire => 'Fire',
            self::Drowning => 'Drowning',
            self::Hazmat => 'Hazardous Materials',
            self::Ems => 'Emergency Medical Services',
            self::PatientTransport => 'Patient Transport',
            self::Other => 'Other',
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
