<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Store support phone and email (shown in the header/footer, contact page and on invoices). */
return new class extends Migration
{
    private const CONTACT = [
        'support_phone' => '+91 77366 88668',
        'support_email' => 'motogears46@gmail.com',
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::CONTACT as $key => $value) {
            DB::table('settings')->where('key', $key)->exists()
                ? DB::table('settings')->where('key', $key)->update(['value' => $value, 'is_public' => true, 'updated_at' => $now])
                : DB::table('settings')->insert(['key' => $key, 'value' => $value, 'type' => 'string', 'group' => 'store', 'is_public' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        Cache::forget('settings.all');
    }

    public function down(): void {}
};
