<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->index();
            $table->integer('quantity')->default(0);   // physically on hand
            $table->integer('reserved')->default(0);   // allocated to open orders
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('allow_backorder')->default(false);
            $table->string('location', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->index(); // stock_received, order_reserved, order_cancelled, order_shipped, manual_adjustment, return
            $table->integer('quantity');         // signed change applied
            $table->integer('quantity_after');
            $table->integer('reserved_after');
            $table->nullableMorphs('reference');
            $table->string('reference_label', 100)->nullable(); // e.g. order number
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventories');
    }
};
