<?php

namespace App\Services\Payments\Upi;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Renders a upi://pay link as an SVG QR code (data URI) — no external QR service involved. */
final class UpiQr
{
    public static function dataUri(string $link, int $size = 280): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($link));
    }
}
