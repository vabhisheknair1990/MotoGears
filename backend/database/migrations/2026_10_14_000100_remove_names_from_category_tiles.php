<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The generated category tiles had the category name drawn into the SVG, and the storefront
 * writes the name over the tile as well — so it showed twice. Strip the drawn name (and the
 * small accent bar under it) from the existing generated tiles. Uploaded images are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $disk = Storage::disk(config('filesystems.media_disk', 'public'));
        $rows = DB::table('categories')->whereNotNull('image_path')->get(['id', 'image_path'])
            ->filter(fn ($c) => str_starts_with($c->image_path, 'categories/') && str_ends_with($c->image_path, '.svg'));

        foreach ($rows as $c) {
            try {
                if (! $disk->exists($c->image_path)) {
                    continue;
                }
                $svg = (string) $disk->get($c->image_path);
                $clean = preg_replace(['#<text\b[^>]*>.*?</text>#s', '#<rect x="40" y="350" width="60" height="4"[^>]*/>#'], '', $svg);
                if ($clean === null || $clean === $svg) {
                    continue;
                }
                // A new file name, so browsers don't keep showing the cached old picture.
                $new = preg_replace('#(-v\d+)?\.svg$#', '-v2.svg', $c->image_path);
                $disk->put($new, $clean);
                DB::table('categories')->where('id', $c->id)->update(['image_path' => $new, 'updated_at' => now()]);
                if ($new !== $c->image_path) {
                    $disk->delete($c->image_path);
                }
            } catch (\Throwable) {
                // A missing or unreadable file just keeps its old picture.
            }
        }
        \App\Services\StorefrontCache::flush();
    }

    public function down(): void
    {
        // Pictures only; nothing to undo.
    }
};
