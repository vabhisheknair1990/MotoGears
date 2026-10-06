<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('type', 20); // percentage | fixed | free_shipping
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'expires_at']);
        });

        Schema::create('coupon_product', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });

        Schema::create('category_coupon', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'category_id']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('token', 64)->nullable()->unique(); // guest cart token
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shipping_method', 30)->default('standard');
            $table->timestamps();
            $table->index('updated_at');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['wishlist_id', 'product_id']);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 30)->default('Home'); // Home, Work, Other
            $table->string('name');
            $table->string('phone', 20);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('postal_code', 12);
            $table->string('country', 2)->default('IN');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'is_default']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('payment_status', 20)->default('pending'); // pending, paid, failed, refunded
            $table->string('payment_method', 30);
            $table->string('shipping_method', 30)->default('standard');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code', 40)->nullable();
            $table->json('billing_address');
            $table->json('shipping_address');
            $table->string('tracking_number', 60)->nullable();
            $table->string('carrier', 60)->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index('placed_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_slug')->nullable();
            $table->string('sku', 64);
            $table->string('part_number', 100)->nullable();
            $table->string('brand_name')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('mrp', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
            $table->index('product_id');
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('comment')->nullable();
            $table->boolean('is_customer_visible')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 30);   // cod, demo_card, demo_upi (razorpay, stripe later)
            $table->string('gateway', 30);  // cod, demo
            $table->string('transaction_id', 100)->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('status', 20)->default('initiated'); // initiated, pending, success, failed, refunded
            $table->string('failure_reason')->nullable();
            $table->json('meta')->nullable(); // masked card, vpa, etc. never raw card data
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamps();
            $table->index(['coupon_id', 'user_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'user_id']);
            $table->index(['product_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('review_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['review_images', 'reviews', 'coupon_usages', 'payments', 'order_status_histories', 'order_items', 'orders',
            'addresses', 'wishlist_items', 'wishlists', 'cart_items', 'carts', 'category_coupon', 'coupon_product', 'coupons'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
