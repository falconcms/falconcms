<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Languages Table
        Schema::create('cms_languages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 5)->unique(); // en, bn, fr, etc.
            $table->string('flag', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // 2. Post Translations Table
        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->string('locale', 5);
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();

            // SEO Fields
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->unique(['post_id', 'locale']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('post_translations');
        Schema::dropIfExists('cms_languages');
    }
};
