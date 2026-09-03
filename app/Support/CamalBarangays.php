<?php

namespace App\Support;

/**
 * Real barangays of Camalig, Albay (the municipality formerly known as Camal).
 *
 * Source: PSA 2010 Census of Population and Housing for Albay.
 */
final class CamalBarangays
{
    public const ALL = [
        'Anoling', 'Baligang', 'Bantonan', 'Bariw', 'Binanderahan', 'Binitayan', 'Bongabong',
        'Cabagñan', 'Cabraran Pequeño', 'Caguiba', 'Calabidongan', 'Comun', 'Cotmon',
        'Del Rosario', 'Gapo', 'Gotob', 'Ilawod', 'Iluluan', 'Libod', 'Ligban', 'Mabunga',
        'Magogon', 'Manawan', 'Maninila', 'Mina', 'Miti', 'Palanog', 'Panoypoy', 'Pariaan',
        'Quinartilan', 'Quirangay', 'Quitinday', 'Salugan', 'Solong', 'Sua', 'Sumlang',
        'Tagaytay', 'Tagoytoy', 'Taladong', 'Taloto', 'Taplacon', 'Tinago', 'Tumpa',
        'Barangay 1 (Pob.)', 'Barangay 2 (Pob.)', 'Barangay 3 (Pob.)', 'Barangay 4 (Pob.)',
        'Barangay 5 (Pob.)', 'Barangay 6 (Pob.)', 'Barangay 7 (Pob.)',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::ALL;
    }
}
