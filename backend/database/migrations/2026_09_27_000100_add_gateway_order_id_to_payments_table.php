<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The provider-side order reference (e.g. Razorpay "order_XXXX") used to match callbacks and webhooks. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_order_id', 64)->nullable()->after('gateway');
            $table->index('gateway_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gateway_order_id']);
            $table->dropColumn('gateway_order_id');
        });
    }
};
