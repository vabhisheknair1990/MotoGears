<?php

namespace App\Support;

/** GST state codes (the first two digits of a GSTIN) → state / union territory names. */
final class GstStates
{
    public const CODES = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand',
        '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya',
        '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh',
        '24' => 'Gujarat', '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa',
        '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
    ];

    /** State of the business that holds this GSTIN (null if it isn't a valid-looking GSTIN). */
    public static function fromGstin(?string $gstin): ?string
    {
        $gstin = strtoupper(trim((string) $gstin));

        return preg_match('/^\d{2}[A-Z0-9]{13}$/', $gstin) ? (self::CODES[substr($gstin, 0, 2)] ?? null) : null;
    }

    public static function codeOf(?string $state): ?string
    {
        $code = array_search(self::normalise($state), array_map(self::normalise(...), self::CODES), true);

        return $code === false ? null : (string) $code;
    }

    public static function same(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && self::normalise($a) === self::normalise($b);
    }

    private static function normalise(?string $state): string
    {
        return preg_replace('/[^a-z]/', '', str_replace('&', 'and', strtolower((string) $state)));
    }
}
