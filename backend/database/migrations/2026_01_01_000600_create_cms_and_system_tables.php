<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('eyebrow', 80)->nullable();
            $table->string('desktop_image_path')->nullable();
            $table->string('mobile_image_path')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url')->nullable();
            $table->string('placement', 30)->default('hero'); // hero, promo, offer
            $table->string('theme', 20)->default('dark');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['placement', 'is_active', 'sort_order']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60)->default('General')->index();
            $table->string('question');
            $table->text('answer');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content');
            $table->string('cover_image_path')->nullable();
            $table->string('status', 20)->default('draft'); // draft, published
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(3);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blog_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['blog_post_id', 'blog_tag_id']);
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location', 100)->nullable();
            $table->string('vehicle', 100)->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('content');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('subject');
            $table->text('message');
            $table->string('status', 20)->default('new'); // new, read, replied, closed
            $table->text('admin_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('status', 20)->default('subscribed'); // subscribed, unsubscribed
            $table->string('source', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('search_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 100)->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->unsignedInteger('results')->default(0);
            $table->timestamps();
            $table->index('hits');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, number, boolean, json
            $table->string('group', 40)->default('general')->index();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'audit_logs', 'settings', 'search_terms', 'newsletter_subscribers', 'contact_messages',
            'testimonials', 'blog_post_tag', 'blog_tags', 'blog_posts', 'blog_categories', 'faqs', 'pages', 'banners'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
