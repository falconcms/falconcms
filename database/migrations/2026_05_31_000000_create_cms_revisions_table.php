<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_revisions')) {
            return;
        }

        Schema::create('cms_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisionable_type');
            $table->unsignedBigInteger('revisionable_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type', 20)->default('revision'); // revision | autosave
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['revisionable_type', 'revisionable_id', 'type'], 'cms_rev_morph_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_revisions');
    }
};
