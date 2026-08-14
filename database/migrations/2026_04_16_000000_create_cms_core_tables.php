<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Post Types
        Schema::create('post_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('singular_name')->nullable();
            $table->text('description')->nullable();
            $table->text('icon')->nullable();
            $table->boolean('is_builtin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('has_archive')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('show_in_menu')->default(true);
            $table->boolean('show_in_rest')->default(true);
            $table->boolean('hierarchical')->default(false);
            $table->boolean('exclude_from_search')->default(false);
            $table->boolean('publicly_queryable')->default(true);
            $table->json('supports')->nullable(); // title, editor, thumbnail, etc.
            $table->softDeletes();
            $table->timestamps();
        });

        DB::table('post_types')->insert([
            ['name' => 'Posts', 'slug' => 'post', 'is_builtin' => true, 'created_at' => now(), 'updated_at' => now(), 'supports' => json_encode(['title', 'editor', 'thumbnail', 'excerpt', 'comments'])],
            ['name' => 'Pages', 'slug' => 'page', 'is_builtin' => true, 'created_at' => now(), 'updated_at' => now(), 'supports' => json_encode(['title', 'editor', 'thumbnail'])],
        ]);

        // 2. Posts Table
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('type')->default('post')->index();
            $table->string('status')->default('draft')->index();
            $table->string('lang_code', 5)->default('en')->index();
            $table->unsignedBigInteger('origin_id')->nullable()->index();
            $table->string('featured_image')->nullable();
            $table->string('editor_type')->default('rich');
            $table->string('template')->nullable();
            $table->integer('menu_order')->default(0);
            $table->timestamp('published_at')->nullable();

            $table->json('gallery')->nullable();
            $table->json('seo_meta')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['slug', 'type', 'lang_code'], 'posts_slug_type_lang_unique');
        });

        // 3. Menus Table
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->cascadeOnDelete();
            $table->string('title');
            $table->string('route')->nullable();
            $table->text('params')->nullable();
            $table->text('icon')->nullable();
            $table->string('group')->nullable();
            $table->string('permission')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // 4. Taxonomies & Pivot Tables
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('lang_code', 10)->default('en')->index();
            $table->unsignedBigInteger('origin_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'lang_code']);
        });

        Schema::create('category_post', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->primary(['category_id', 'post_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('lang_code', 10)->default('en')->index();
            $table->unsignedBigInteger('origin_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'lang_code']);
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('category_post');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_types');
    }
};
