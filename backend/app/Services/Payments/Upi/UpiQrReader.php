<?php

namespace App\Services\Payments\Upi;

use Throwable;
use Zxing\QrReader;

/**
 * Reads the upi://pay link out of a QR code image (Razorpay sends its UPI QR codes as a
 * branded poster PNG). We re-draw the code from that link so it fits the payment page.
 */
class UpiQrReader
{
    public function read(string $png): ?string
    {
        try {
            $text = (new QrReader($png, QrReader::SOURCE_TYPE_BLOB))->text();
        } catch (Throwable) {
            return null;
        }

        return is_string($text) && str_starts_with(strtolower($text), 'upi://pay?') ? $text : null;
    }

    /** The amount (in rupees) encoded in a upi://pay link, or null. */
    public static function amount(string $link): ?float
    {
        parse_str((string) parse_url($link, PHP_URL_QUERY), $q);

        return isset($q['am']) && is_numeric($q['am']) ? (float) $q['am'] : null;
    }
}
