<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media
{
    /** Convert a stored path into a public URL. Absolute URLs are returned untouched. */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        return Storage::disk(config('filesystems.media_disk', 'public'))->url($path);
    }

    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('filesystems.media_disk', 'public'));
    }
}
