<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('vehicle_type', 20)->index(); // car | motorcycle | both
            $table->string('country', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_manufacturer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('vehicle_type', 20)->index(); // car | motorcycle
            $table->string('body_type', 40)->nullable(); // SUV, Hatchback, Cruiser...
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['vehicle_manufacturer_id', 'slug']);
        });

        Schema::create('vehicle_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_model_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('year_from');
            $table->unsignedSmallInteger('year_to')->nullable(); // null = still in production
            $table->string('engine', 100)->nullable();
            $table->string('fuel_type', 30)->nullable();       // Petrol, Diesel, CNG, Electric, Hybrid
            $table->string('transmission', 30)->nullable();    // MT, AT, AMT, CVT, DCT
            $table->unsignedInteger('displacement_cc')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['vehicle_model_id', 'year_from', 'year_to']);
        });

        Schema::create('product_vehicle_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_manufacturer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_model_id')->nullable()->constrained()->cascadeOnDelete();   // null = all models
            $table->foreignId('vehicle_variant_id')->nullable()->constrained()->cascadeOnDelete(); // null = all variants
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_variant_id', 'product_id'], 'pvc_variant_product_idx');
            $table->index(['vehicle_model_id', 'product_id'], 'pvc_model_product_idx');
            $table->index(['vehicle_manufacturer_id', 'product_id'], 'pvc_manufacturer_product_idx');
        });

        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('nickname', 60)->nullable();
            $table->string('registration_number', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'vehicle_variant_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vehicles');
        Schema::dropIfExists('product_vehicle_compatibilities');
        Schema::dropIfExists('vehicle_variants');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('vehicle_manufacturers');
    }
};
