<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks installed/active plugins. Plugin CODE lives in the app's plugins/
 * directory (drop-in, like themes); this table only records which of the
 * discovered plugins are installed and active, plus the version we last
 * activated (for update detection later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('version')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
