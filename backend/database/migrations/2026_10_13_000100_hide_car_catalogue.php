<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The store does not sell car parts for now: add the "Sell car parts" setting (off), mark
 * car-specific "universal" products as car products, take the car-only blog posts offline and
 * reword the seeded copy that promised car parts. Nothing is deleted — switching the setting on
 * in Admin → Settings brings the car catalogue back (the blog posts can be republished).
 */
return new class extends Migration
{
    private const COPY = [
        'banners' => ['subtitle' => [
            'Over 170 genuine parts and accessories with guaranteed fitment for 40+ Indian cars and bikes.' => 'Genuine bike parts, riding gear and accessories with guaranteed fitment for India\'s favourite motorcycles.',
            'Fully synthetic protection for bikes and cars.' => 'Fully synthetic protection for your bike.',
        ]],
        'pages' => ['content' => [
            'for Indian cars, SUVs and motorcycles' => 'for Indian motorcycles and scooters',
            'building a weekend Thar, we are here to help you ride and drive with confidence.' => 'building a weekend touring bike, we are here to help you ride with confidence.',
        ]],
        'blog_posts' => ['content' => self::BLOG],
    ];

    private const BLOG = [
            'most important wear item on your car or bike,' => 'most important wear item on your bike,',
            'A pulsing brake pedal usually means warped discs.' => 'A pulsing brake lever usually means warped discs.',
            'front pads typically last 20,000–35,000 km on cars and 10,000–15,000 km on motorcycles.' => 'front pads typically last 10,000–15,000 km on motorcycles.',
            '<h2>Cars</h2><p>Fully synthetic oils like Castrol EDGE or Mobil 1 typically last 10,000 km or one year. If you drive mostly in stop-and-go traffic, halve that interval.</p>' => '',
            'but Indian driving conditions are tough on oil.' => 'but Indian riding conditions are tough on oil.',
    ];


    private const CAR_POSTS = ['5 Upgrades Every New Mahindra Thar Owner Should Consider', 'Monsoon Car Care: A Complete Guide'];

    public function up(): void
    {
        $now = now();
        if (! DB::table('settings')->where('key', 'sell_car_parts')->exists()) {
            DB::table('settings')->insert(['key' => 'sell_car_parts', 'value' => '0', 'type' => 'boolean', 'group' => 'catalog', 'is_public' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        // "Car First Aid Kit", "3M Car Shampoo"… were filed as universal: they are car products.
        DB::table('products')->where('vehicle_type', 'universal')->get(['id', 'name'])
            ->filter(fn ($p) => preg_match('/\bcars?\b/i', $p->name))
            ->each(fn ($p) => DB::table('products')->where('id', $p->id)->update(['vehicle_type' => 'car', 'updated_at' => $now]));

        DB::table('blog_posts')->whereIn('title', self::CAR_POSTS)->where('status', 'published')->update(['status' => 'draft', 'updated_at' => $now]);

        foreach (self::COPY as $table => $columns) {
            foreach ($columns as $column => $replacements) {
                foreach (DB::table($table)->get(['id', $column]) as $row) {
                    $text = strtr((string) $row->{$column}, $replacements);
                    if ($text !== (string) $row->{$column}) {
                        $update = [$column => $text, 'updated_at' => $now];
                        if ($table === 'blog_posts') { // excerpts are the opening of the post text
                            $update['excerpt'] = Str::limit(strip_tags($text), 170);
                            $update['meta_description'] = Str::limit(strip_tags($text), 150);
                        }
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                }
            }
        }

        Cache::forget('settings.all');
        foreach (['storefront.homepage', 'storefront.category_tree', 'storefront.brands', 'storefront.menu'] as $key) {
            Cache::forget($key);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'sell_car_parts')->delete();
        Cache::forget('settings.all');
    }
};
