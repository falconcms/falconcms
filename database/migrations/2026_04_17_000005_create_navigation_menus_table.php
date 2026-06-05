<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->nullable();
            $table->string('location')->nullable(); 
            $table->string('lang_code', 5)->default('en')->index();
            $table->boolean('is_header')->default(false);
            $table->boolean('is_footer')->default(false);
            $table->timestamps();
        });

        Schema::create('navigation_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('navigation_menu_id')->constrained('navigation_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('navigation_menu_items')->cascadeOnDelete();
            $table->string('title');
            $table->string('url')->nullable();
            $table->string('type')->default('custom'); // custom, post, page, category
            $table->unsignedBigInteger('object_id')->nullable(); // post_id, category_id
            $table->string('target')->default('_self');
            $table->string('classes')->nullable();
            // Merged from former add_icon_to_navigation_menu_items migration
            $table->string('icon')->nullable();
            $table->boolean('show_only_icon')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_menu_items');
        Schema::dropIfExists('navigation_menus');
    }
};
