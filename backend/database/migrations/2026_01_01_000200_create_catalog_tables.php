<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('vehicle_type', 20)->default('universal')->index(); // car | motorcycle | universal
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['parent_id', 'is_active', 'sort_order']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('country', 60)->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku', 64)->unique();
            $table->string('part_number', 100)->nullable()->index();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('mrp', 12, 2);
            $table->decimal('price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(18);
            $table->string('vehicle_type', 20)->default('car')->index(); // car | motorcycle | universal
            $table->boolean('is_universal')->default(false)->index();
            $table->string('position', 60)->nullable();      // Front / Rear / Left / Right
            $table->string('material', 100)->nullable();
            $table->string('dimensions', 100)->nullable();
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->string('warranty', 150)->nullable();
            $table->text('installation_info')->nullable();
            $table->json('whats_included')->nullable();
            $table->json('specifications')->nullable();
            $table->json('tags')->nullable();
            $table->string('video_url')->nullable();
            $table->text('search_keywords')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'category_id']);
            $table->index(['is_active', 'brand_id']);
            $table->index(['is_active', 'price']);
            $table->index(['is_active', 'is_featured']);
            $table->index(['is_active', 'sold_count']);
            $table->index(['is_active', 'created_at']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('name');                 // e.g. "Black / 6000K"
            $table->json('options')->nullable();    // {"Colour":"Black","Colour Temp":"6000K"}
            $table->decimal('price_adjustment', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_filterable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'slug']);
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_id', 'attribute_value_id']);
            $table->index('attribute_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('product_faqs');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
