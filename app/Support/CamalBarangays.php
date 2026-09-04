<?php

namespace App\Support;

/**
 * Real barangays of Camalaniugan, Cagayan (Region II - Cagayan Valley).
 *
 * Source: PSA Census of Population and Housing for Cagayan.
 */
final class CamalBarangays
{
    public const ALL = [
        'Abagao', 'Afunan Cabayu', 'Agusi', 'Alilinu', 'Baggao', 'Bantay',
        'Bulala', 'Casili Norte', 'Casili Sur', 'Catotoran Norte', 'Catotoran Sur',
        'Centro Norte', 'Centro Sur', 'Cullit', 'Dacal-Lafugu', 'Dammang Norte',
        'Dammang Sur', 'Dugo', 'Fusina', 'Gang-ngo', 'Jurisdiction', 'Luec', 'Minanga',
        'Paragat', 'Sapping', 'Tagum', 'Tuluttuging', 'Ziminila',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::ALL;
    }
}
