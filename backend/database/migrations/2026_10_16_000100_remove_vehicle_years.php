<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The store no longer uses model years: vehicles are picked as make → model → variant, and a
 * part fits a make, a model or a variant (whatever year). Drops the year columns from
 *  - vehicle_variants (year_from, year_to),
 *  - product_vehicle_compatibilities (year_from, year_to),
 *  - customer_vehicles (year),
 * after merging rows that would become duplicates without their years.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Fitment rows that differed only by year: keep the first of each.
        DB::table('product_vehicle_compatibilities')
            ->orderBy('id')->get(['id', 'product_id', 'vehicle_manufacturer_id', 'vehicle_model_id', 'vehicle_variant_id'])
            ->groupBy(fn ($r) => implode('|', [$r->product_id, $r->vehicle_manufacturer_id, $r->vehicle_model_id, $r->vehicle_variant_id]))
            ->each(function ($rows) {
                if ($rows->count() > 1) {
                    DB::table('product_vehicle_compatibilities')->whereIn('id', $rows->slice(1)->pluck('id'))->delete();
                }
            });

        // The same bike saved twice in a garage with different years: keep one (the default one if any).
        DB::table('customer_vehicles')
            ->orderByDesc('is_default')->orderBy('id')->get(['id', 'user_id', 'vehicle_variant_id'])
            ->groupBy(fn ($r) => $r->user_id.'|'.$r->vehicle_variant_id)
            ->each(function ($rows) {
                if ($rows->count() > 1) {
                    DB::table('customer_vehicles')->whereIn('id', $rows->slice(1)->pluck('id'))->delete();
                }
            });

        Schema::table('customer_vehicles', function (Blueprint $table) {
            $table->unique(['user_id', 'vehicle_variant_id']);
        });
        Schema::table('customer_vehicles', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'vehicle_variant_id', 'year']);
        });
        Schema::table('customer_vehicles', function (Blueprint $table) {
            $table->dropColumn('year');
        });

        // The foreign key on vehicle_model_id needs an index of its own once the year index goes.
        Schema::table('vehicle_variants', function (Blueprint $table) {
            $table->index('vehicle_model_id');
        });
        Schema::table('vehicle_variants', function (Blueprint $table) {
            $table->dropIndex(['vehicle_model_id', 'year_from', 'year_to']);
        });
        Schema::table('vehicle_variants', function (Blueprint $table) {
            $table->dropColumn(['year_from', 'year_to']);
        });

        Schema::table('product_vehicle_compatibilities', function (Blueprint $table) {
            $table->dropColumn(['year_from', 'year_to']);
        });

        \App\Services\StorefrontCache::flush();
    }

    /** Brings the columns back (empty — the old years are not restored). */
    public function down(): void
    {
        Schema::table('product_vehicle_compatibilities', function (Blueprint $table) {
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
        });
        Schema::table('vehicle_variants', function (Blueprint $table) {
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->index(['vehicle_model_id', 'year_from', 'year_to']);
        });
        Schema::table('customer_vehicles', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable();
            $table->unique(['user_id', 'vehicle_variant_id', 'year']);
        });
    }
};
