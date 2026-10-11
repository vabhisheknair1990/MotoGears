<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guest checkout: orders without an account. Every order now keeps a snapshot of the
 * customer's contact details (name / email / phone), so guest and registered orders are
 * handled the same way by emails, invoices, admin search and order tracking. Guest orders
 * carry a random access token (the "secret link" in the confirmation email).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('customer_name', 120)->nullable()->after('user_id');
            $table->string('customer_email', 190)->nullable()->after('customer_name')->index();
            $table->string('customer_phone', 20)->nullable()->after('customer_email');
            $table->boolean('is_guest')->default(false)->after('customer_phone')->index();
            $table->string('guest_token', 64)->nullable()->unique()->after('is_guest');
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('email', 190)->nullable()->after('user_id');
            $table->index(['coupon_id', 'email']);
        });

        // Backfill the contact snapshot for existing (registered) orders and coupon usage.
        DB::table('orders')->whereNotNull('user_id')->whereNull('customer_email')->orderBy('id')
            ->chunkById(500, function ($orders) {
                $users = DB::table('users')->whereIn('id', $orders->pluck('user_id')->unique())->get(['id', 'name', 'email', 'phone'])->keyBy('id');
                foreach ($orders as $o) {
                    $u = $users->get($o->user_id);
                    if ($u) {
                        DB::table('orders')->where('id', $o->id)->update(['customer_name' => $u->name, 'customer_email' => strtolower($u->email), 'customer_phone' => $u->phone]);
                    }
                }
            });
        DB::table('coupon_usages')->whereNotNull('user_id')->whereNull('email')->orderBy('id')
            ->chunkById(500, function ($rows) {
                $emails = DB::table('users')->whereIn('id', $rows->pluck('user_id')->unique())->pluck('email', 'id');
                foreach ($rows as $r) {
                    DB::table('coupon_usages')->where('id', $r->id)->update(['email' => strtolower((string) ($emails[$r->user_id] ?? ''))]);
                }
            });

        if (! DB::table('settings')->where('key', 'guest_checkout_enabled')->exists()) {
            DB::table('settings')->insert(['key' => 'guest_checkout_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'checkout',
                'is_public' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'guest_checkout_enabled')->delete();
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropIndex(['coupon_id', 'email']);
            $table->dropColumn('email');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['guest_token']);
            $table->dropIndex(['customer_email']);
            $table->dropIndex(['is_guest']);
            $table->dropColumn(['customer_name', 'customer_email', 'customer_phone', 'is_guest', 'guest_token']);
        });
    }
};
