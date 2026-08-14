<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal API tokens for the REST API. The plaintext token is shown to the user once;
 * only its SHA-256 hash is stored. Each token belongs to a user, so API requests act as
 * that user and are subject to the same role/permission checks as the dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_tokens')) {
            return;
        }

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token', 64)->unique();   // sha256 hex
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
