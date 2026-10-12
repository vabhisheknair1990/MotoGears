<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Business details from the amended GST registration certificate (Form GST REG-06, issued
 * 09/03/2022): GSTIN, legal & trade name and principal place of business. Also moves the
 * seeded "About" / "Shipping policy" copy from Bengaluru to Thiruvananthapuram.
 */
return new class extends Migration
{
    private const DETAILS = [
        'gstin' => '32AWCPT0591R2ZY',
        'trade_name' => 'M/S MOTO GEARS',
        'legal_name' => 'Ashwin Joe Thomas',
        'store_address' => 'Oolanpara Road, Ground Floor, TC 31/3483/3, Ambalamukku, Perurnagar, Thiruvananthapuram, Kerala 695005',
    ];

    private const COPY = [
        'MotoGears was started in Bengaluru in 2019' => 'MotoGears was started in Thiruvananthapuram in 2019',
        'We ship across India from our Bengaluru and Gurugram warehouses.' => 'We ship across India from Thiruvananthapuram, Kerala.',
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::DETAILS as $key => $value) {
            $exists = DB::table('settings')->where('key', $key)->exists();
            $exists
                ? DB::table('settings')->where('key', $key)->update(['value' => $value, 'is_public' => true, 'updated_at' => $now])
                : DB::table('settings')->insert(['key' => $key, 'value' => $value, 'type' => 'string', 'group' => 'store', 'is_public' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        if (DB::getSchemaBuilder()->hasTable('pages')) {
            foreach (DB::table('pages')->whereIn('slug', ['about', 'shipping-policy'])->get(['id', 'content']) as $page) {
                $content = strtr((string) $page->content, self::COPY);
                if ($content !== $page->content) {
                    DB::table('pages')->where('id', $page->id)->update(['content' => $content, 'updated_at' => $now]);
                }
            }
        }

        Cache::forget('settings.all');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['trade_name', 'legal_name'])->delete();
        Cache::forget('settings.all');
    }
};
