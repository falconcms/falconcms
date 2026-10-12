<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly search volumes the site owner imported from Google Keyword Planner (or any keyword
 * tool's CSV export), for the editor's keyword ideas. A free account gets ranges ("1K-10K"),
 * hence a low and a high figure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_keyword_volumes')) {
            return;
        }
        Schema::create('cms_keyword_volumes', function (Blueprint $table) {
            $table->id();
            $table->char('keyword_hash', 40)->unique();
            $table->string('keyword', 255);
            $table->unsignedInteger('searches_low')->nullable();
            $table->unsignedInteger('searches_high')->nullable()->index();
            $table->string('competition', 20)->nullable();
            $table->timestamp('imported_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_keyword_volumes');
    }
};
