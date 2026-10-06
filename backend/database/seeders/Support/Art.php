<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Generates clean vector artwork for seeded catalogue data (product shots, brand wordmarks,
 * banners, category tiles) and stores it on the public disk exactly like an admin upload would.
 * Real product photos can replace these at any time through the admin image uploader.
 */
class Art
{
    private const PALETTES = [
        'brake' => ['#1f2937', '#7f1d1d', '#ef4444'],
        'light' => ['#0f172a', '#1e3a8a', '#fbbf24'],
        'filter' => ['#111827', '#374151', '#60a5fa'],
        'oil' => ['#1c1917', '#78350f', '#f59e0b'],
        'suspension' => ['#111827', '#3f3f46', '#f97316'],
        'chain' => ['#18181b', '#3f3f46', '#a3a3a3'],
        'battery' => ['#052e16', '#166534', '#4ade80'],
        'electrical' => ['#0c0a09', '#1e40af', '#38bdf8'],
        'exhaust' => ['#0a0a0a', '#44403c', '#fb923c'],
        'interior' => ['#1e1b4b', '#4338ca', '#a5b4fc'],
        'exterior' => ['#0f172a', '#0e7490', '#67e8f9'],
        'safety' => ['#1c1917', '#b91c1c', '#fde047'],
        'care' => ['#083344', '#0891b2', '#a5f3fc'],
        'touring' => ['#1c1917', '#57534e', '#d6d3d1'],
        'default' => ['#111827', '#1f2937', '#ef4444'],
    ];

    public static function disk()
    {
        return Storage::disk(config('filesystems.media_disk', 'public'));
    }

    public static function put(string $path, string $svg): string
    {
        self::disk()->put($path, $svg);

        return $path;
    }

    /** Product shot. $variant 0 = hero, 1 = detail, 2 = "in the box". */
    public static function product(string $path, string $name, string $brand, string $icon, string $palette, int $variant = 0): string
    {
        [$bg1, $bg2, $accent] = self::PALETTES[$palette] ?? self::PALETTES['default'];
        $iconSvg = self::icon($icon, $accent);
        $title = self::wrap($name, 26, 3);
        $lines = '';
        foreach ($title as $i => $line) {
            $lines .= '<text x="60" y="'.(640 + $i * 40).'" font-size="32" font-weight="700" fill="#fff">'.htmlspecialchars($line, ENT_XML1).'</text>';
        }
        $tag = ['PRODUCT', 'DETAIL VIEW', 'IN THE BOX'][$variant] ?? 'PRODUCT';
        $brandText = htmlspecialchars(strtoupper($brand), ENT_XML1);
        $grid = '';
        for ($i = 1; $i < 8; $i++) {
            $grid .= '<path d="M'.($i * 100).' 0 V800 M0 '.($i * 100).' H800"/>';
        }
        $scale = [1, 1.35, 0.8][$variant] ?? 1;
        $rotate = [0, -12, 8][$variant] ?? 0;
        $scaleOut = round($scale * 1.2, 2);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="$bg2"/><stop offset="1" stop-color="$bg1"/></linearGradient>
    <radialGradient id="glow" cx="0.5" cy="0.42" r="0.45"><stop offset="0" stop-color="$accent" stop-opacity="0.35"/><stop offset="1" stop-color="$accent" stop-opacity="0"/></radialGradient>
  </defs>
  <rect width="800" height="800" fill="url(#g)"/>
  <rect width="800" height="800" fill="url(#glow)"/>
  <g opacity="0.08" stroke="#fff" stroke-width="1">$grid</g>
  <text x="60" y="84" font-family="Arial, Helvetica, sans-serif" font-size="30" font-weight="800" letter-spacing="3" fill="$accent">$brandText</text>
  <text x="740" y="84" text-anchor="end" font-family="Arial, Helvetica, sans-serif" font-size="16" letter-spacing="3" fill="#ffffff" opacity="0.6">$tag</text>
  <g transform="translate(400 350) rotate($rotate) scale($scaleOut)">$iconSvg</g>
  <rect x="60" y="600" width="80" height="4" fill="$accent"/>
  <g font-family="Arial, Helvetica, sans-serif">$lines</g>
</svg>
SVG;

        return self::put($path, self::fill($svg));
    }

    public static function wordmark(string $path, string $name, string $color = '#111827', string $accent = '#ef4444'): string
    {
        $size = strlen($name) > 10 ? 44 : 56;
        $nameText = htmlspecialchars($name, ENT_XML1);
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 200" width="400" height="200">
  <rect width="400" height="200" rx="16" fill="#ffffff"/>
  <text x="200" y="118" text-anchor="middle" font-family="Arial Black, Arial, Helvetica, sans-serif" font-size="$size" font-weight="900" fill="$color" letter-spacing="1">$nameText</text>
  <rect x="150" y="142" width="100" height="6" rx="3" fill="$accent"/>
</svg>
SVG;

        return self::put($path, self::fill($svg));
    }

    public static function tile(string $path, string $label, string $icon, string $palette): string
    {
        [$bg1, $bg2, $accent] = self::PALETTES[$palette] ?? self::PALETTES['default'];
        $iconSvg = self::icon($icon, $accent);
        $labelText = htmlspecialchars($label, ENT_XML1);
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400" width="600" height="400">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="$bg2"/><stop offset="1" stop-color="$bg1"/></linearGradient></defs>
  <rect width="600" height="400" fill="url(#g)"/>
  <g transform="translate(430 200) scale(0.8)" opacity="0.95">$iconSvg</g>
  <text x="40" y="330" font-family="Arial, Helvetica, sans-serif" font-size="40" font-weight="800" fill="#fff">$labelText</text>
  <rect x="40" y="350" width="60" height="4" fill="$accent"/>
</svg>
SVG;

        return self::put($path, self::fill($svg));
    }

    public static function banner(string $path, string $title, string $icon, string $palette, bool $mobile = false): string
    {
        [$bg1, $bg2, $accent] = self::PALETTES[$palette] ?? self::PALETTES['default'];
        $iconSvg = self::icon($icon, $accent);
        [$w, $h, $ix, $iy, $s] = $mobile ? [800, 1000, 400, 640, 1.3] : [1600, 640, 1180, 320, 1.45];
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 $w $h" width="$w" height="$h" preserveAspectRatio="xMidYMid slice">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="$bg1"/><stop offset="0.6" stop-color="$bg2"/><stop offset="1" stop-color="$bg1"/></linearGradient>
    <radialGradient id="glow" cx="0.74" cy="0.5" r="0.5"><stop offset="0" stop-color="$accent" stop-opacity="0.45"/><stop offset="1" stop-color="$accent" stop-opacity="0"/></radialGradient>
  </defs>
  <rect width="$w" height="$h" fill="url(#g)"/>
  <rect width="$w" height="$h" fill="url(#glow)"/>
  <g stroke="$accent" stroke-opacity="0.18" stroke-width="2" fill="none">
    <path d="M-50 {$h} L {$w} -80"/><path d="M100 {$h} L {$w} 40"/><path d="M250 {$h} L {$w} 160"/>
  </g>
  <g transform="translate($ix $iy) scale($s)">$iconSvg</g>
</svg>
SVG;

        return self::put($path, self::fill($svg));
    }

    // ── internals ─────────────────────────────────────────────────────
    private static function fill(string $svg): string
    {
        return preg_replace('/>\s+</', '><', trim($svg));
    }

    private static function wrap(string $text, int $width, int $maxLines): array
    {
        $lines = explode("\n", wordwrap($text, $width, "\n", true));
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim(mb_substr($lines[$maxLines - 1], 0, $width - 1)).'…';
        }

        return $lines;
    }

    /** Minimal line-art icon set, each drawn around (0,0) within ±180px. */
    public static function icon(string $name, string $a): string
    {
        $s = 'stroke="'.$a.'" stroke-width="10" fill="none" stroke-linecap="round" stroke-linejoin="round"';
        $w = 'stroke="#ffffff" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round"';

        return match ($name) {
            'disc' => "<circle r='170' $w/><circle r='120' $s/><circle r='50' $w/>".implode('', array_map(fn ($i) => "<circle cx='".round(cos($i) * 85)."' cy='".round(sin($i) * 85)."' r='10' fill='$a'/>", range(0, 5)))."<path d='M 120 -100 A 160 160 0 0 1 150 60' stroke='$a' stroke-width='26' fill='none'/>",
            'pad' => "<path d='M-160 -40 Q 0 -140 160 -40 L 140 60 Q 0 -20 -140 60 Z' $w/><path d='M-140 -20 Q 0 -110 140 -20' $s/><rect x='-150' y='80' width='300' height='40' rx='12' $s/>",
            'bulb' => "<path d='M0 -170 A 110 110 0 0 1 70 30 L 60 90 L -60 90 L -70 30 A 110 110 0 0 1 0 -170 Z' $w/><path d='M-50 120 L 50 120 M -40 150 L 40 150' $w/><path d='M-160 -80 L -120 -60 M 160 -80 L 120 -60 M 0 -200 L 0 -180 M -110 -170 L -90 -140 M 110 -170 L 90 -140' $s/>",
            'headlight' => "<path d='M-170 -90 Q 0 -150 170 -90 L 150 100 Q 0 140 -150 100 Z' $w/><circle cx='-60' cy='0' r='55' $s/><circle cx='70' cy='0' r='40' $s/><path d='M-150 80 L 150 80' $s/>",
            'filter' => "<rect x='-160' y='-110' width='320' height='220' rx='18' $w/>".implode('', array_map(fn ($i) => "<path d='M ".(-130 + $i * 26)." -90 L ".(-130 + $i * 26)." 90' $s/>", range(0, 10))),
            'oil' => "<path d='M-90 -120 L 60 -120 L 110 -70 L 110 170 L -90 170 Z' $w/><rect x='-60' y='-170' width='70' height='50' rx='8' $w/><rect x='-60' y='-20' width='140' height='110' rx='10' $s/><path d='M-40 20 L 60 20 M -40 50 L 40 50' $s/>",
            'drop' => "<path d='M0 -170 C 80 -60 130 10 130 70 A 130 130 0 0 1 -130 70 C -130 10 -80 -60 0 -170 Z' $w/><path d='M-60 70 A 60 60 0 0 0 0 130' $s/>",
            'shock' => "<rect x='-40' y='-180' width='80' height='60' rx='12' $w/><path d='M0 -120 L 0 -40' $w/>".implode('', array_map(fn ($i) => "<path d='M-70 ".(-40 + $i * 30)." L 70 ".(-25 + $i * 30)."' $s/>", range(0, 5)))."<rect x='-40' y='130' width='80' height='50' rx='12' $w/>",
            'chain' => implode('', array_map(fn ($i) => "<rect x='".(-170 + $i * 70)."' y='-30' width='80' height='60' rx='30' $w/>", range(0, 4)))."<circle r='150' $s stroke-dasharray='18 22'/>",
            'sprocket' => "<circle r='130' $w/><circle r='50' $s/>".implode('', array_map(fn ($i) => "<rect x='-12' y='-175' width='24' height='40' rx='4' fill='$a' transform='rotate(".($i * 30).")'/>", range(0, 11))),
            'battery' => "<rect x='-160' y='-90' width='320' height='200' rx='16' $w/><rect x='-120' y='-120' width='50' height='30' rx='6' $w/><rect x='70' y='-120' width='50' height='30' rx='6' $w/><path d='M-110 -20 L -70 -20 M -90 -40 L -90 0 M 70 -20 L 110 -20' $s/><path d='M-20 30 L 10 -10 L 0 20 L 30 20 L -5 70' $s/>",
            'spark' => "<rect x='-30' y='-180' width='60' height='60' rx='8' $w/><rect x='-55' y='-120' width='110' height='90' rx='10' $w/><rect x='-35' y='-30' width='70' height='130' rx='10' $s/><path d='M0 100 L 0 150 L 30 150' $w/><path d='M-60 170 L -40 150 M 60 170 L 45 150' $s/>",
            'exhaust' => "<path d='M-180 40 L 40 40 Q 90 40 110 -10 L 150 -110' $w/><ellipse cx='150' cy='-120' rx='40' ry='22' $s/><path d='M-180 90 L 60 90' $s/><circle cx='-100' cy='-60' r='22' $s/><circle cx='-40' cy='-100' r='14' $s/>",
            'wiper' => "<path d='M-170 120 L 130 -150' $w/><path d='M-150 110 L 140 -130' $s/><circle cx='-170' cy='130' r='24' $w/>",
            'clutch' => "<circle r='170' $w/><circle r='110' $s/><circle r='40' $w/>".implode('', array_map(fn ($i) => "<path d='M0 -45 L 0 -105' $s transform='rotate(".($i * 45).")'/>", range(0, 7))),
            'gear' => "<circle r='90' $w/><circle r='40' $s/>".implode('', array_map(fn ($i) => "<rect x='-18' y='-150' width='36' height='55' rx='6' fill='#fff' transform='rotate(".($i * 45).")'/>", range(0, 7))),
            'steering' => "<circle r='160' $w/><circle r='40' $s/><path d='M-160 0 L -40 0 M 40 0 L 160 0 M 0 40 L 0 160' $w/>",
            'radiator' => "<rect x='-170' y='-120' width='340' height='240' rx='10' $w/>".implode('', array_map(fn ($i) => "<path d='M-140 ".(-90 + $i * 30)." L 140 ".(-90 + $i * 30)."' $s/>", range(0, 6))),
            'helmet' => "<path d='M-160 60 A 160 160 0 0 1 160 20 L 160 90 L 40 110 L -160 110 Z' $w/><path d='M0 -40 L 150 -10 L 150 60 L 20 70 Z' $s/>",
            'phone' => "<rect x='-80' y='-160' width='160' height='300' rx='24' $w/><path d='M-150 -40 L -80 -40 M 80 -40 L 150 -40 M -150 -40 L -150 60 M 150 -40 L 150 60' $s/><circle cy='110' r='12' fill='$a'/>",
            'bag' => "<rect x='-160' y='-80' width='320' height='230' rx='26' $w/><path d='M-70 -80 L -70 -130 Q -70 -160 -40 -160 L 40 -160 Q 70 -160 70 -130 L 70 -80' $w/><path d='M-160 20 L 160 20' $s/><rect x='-30' y='0' width='60' height='40' rx='6' $s/>",
            'guard' => "<path d='M-150 150 L -150 -40 Q -150 -150 -40 -150 L 40 -150 Q 150 -150 150 -40 L 150 150' $w/><path d='M-110 150 L -110 -30 Q -110 -110 -30 -110 L 30 -110 Q 110 -110 110 -30 L 110 150' $s/>",
            'camera' => "<rect x='-160' y='-90' width='320' height='190' rx='22' $w/><circle cx='0' cy='5' r='60' $s/><circle cx='0' cy='5' r='25' $w/><rect x='90' y='-70' width='40' height='20' rx='5' fill='$a'/>",
            'mat' => "<path d='M-150 -150 L 150 -150 L 170 150 L -170 150 Z' $w/>".implode('', array_map(fn ($i) => "<path d='M-120 ".(-110 + $i * 45)." L 120 ".(-110 + $i * 45)."' $s/>", range(0, 5))),
            'spray' => "<rect x='-70' y='-60' width='140' height='230' rx='20' $w/><path d='M-40 -60 L -40 -110 L 40 -110 L 40 -60' $w/><path d='M40 -100 L 110 -130 M 120 -150 L 150 -160 M 120 -110 L 160 -110 M 120 -70 L 150 -60' $s/>",
            'pump' => "<rect x='-120' y='-60' width='240' height='170' rx='24' $w/><rect x='-60' y='-20' width='120' height='60' rx='8' $s/><path d='M120 60 Q 190 60 190 -40 L 190 -120' $w/>",
            'shield' => "<path d='M0 -170 L 140 -110 L 140 10 Q 140 120 0 180 Q -140 120 -140 10 L -140 -110 Z' $w/><path d='M-60 0 L -15 45 L 70 -50' $s/>",
            'car' => "<path d='M-180 60 L -160 -10 Q -140 -60 -90 -70 L -40 -120 L 80 -120 L 130 -60 Q 175 -50 180 0 L 180 60 Z' $w/><circle cx='-100' cy='70' r='40' $s/><circle cx='110' cy='70' r='40' $s/>",
            'bike' => "<circle cx='-110' cy='60' r='70' $w/><circle cx='120' cy='60' r='70' $w/><path d='M-110 60 L -30 -40 L 70 -40 L 120 60 M -30 -40 L 10 40 L 70 -40 M 50 -90 L 90 -90' $s/>",
            'tools' => "<path d='M-140 140 L 60 -60 M 60 -60 A 60 60 0 1 1 120 -120 L 90 -90 L 110 -70 L 140 -100 A 60 60 0 0 1 60 -60' $w/><path d='M-140 -140 L 140 140' $s/>",
            'usb' => "<rect x='-60' y='-160' width='120' height='200' rx='18' $w/><rect x='-30' y='-130' width='60' height='40' rx='6' $s/><path d='M0 40 L 0 160 M -40 110 L 0 160 L 40 110' $s/>",
            default => "<circle r='150' $w/><path d='M-80 0 L 80 0 M 0 -80 L 0 80' $s/>",
        };
    }
}
