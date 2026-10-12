<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses visitors asked for that do not exist, with how often and from where, so a broken
 * link or a moved page can be redirected from the Redirects screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_not_found_log')) {
            return;
        }
        Schema::create('cms_not_found_log', function (Blueprint $table) {
            $table->id();
            $table->char('path_hash', 40)->unique();
            $table->string('path', 500);
            $table->unsignedInteger('hits')->default(1);
            $table->text('referrer')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_not_found_log');
    }
};
