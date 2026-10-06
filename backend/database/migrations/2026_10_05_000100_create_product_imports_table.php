<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedInteger('file_size')->default(0);
            $table->string('mode', 20)->default('upsert');          // upsert | create | update
            $table->boolean('auto_start')->default(false);          // import straight after a clean check
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);      // products (units) to process
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);     // all issues, even beyond the stored list
            $table->json('summary')->nullable();                    // result of the check step
            $table->json('errors')->nullable();                     // capped list of issues
            $table->string('message', 500)->nullable();
            $table->boolean('cancel_requested')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
    }
};
