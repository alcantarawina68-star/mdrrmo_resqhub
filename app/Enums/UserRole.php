<?php

namespace App\Enums;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Encoder = 'encoder';
    case BarangayOfficial = 'barangay_official';
    case Responder = 'responder';
    case CommunityUser = 'community_user';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Super Admin',
            self::Admin => 'Administrator',
            self::Encoder => 'Encoder / Dispatcher',
            self::BarangayOfficial => 'Barangay Official',
            self::Responder => 'Responder',
            self::CommunityUser => 'Community User',
        };
    }

    public function isSuperadmin(): bool
    {
        return $this === self::Superadmin;
    }

    /**
     * Roles allowed to verify incidents, assign responders, and post announcements.
     *
     * @return array<int, string>
     */
    public static function operationsRoles(): array
    {
        return [self::Admin->value, self::Encoder->value, self::Superadmin->value];
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
