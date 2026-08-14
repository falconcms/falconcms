<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('comments')) {
            return;
        }

        Schema::table('comments', function (Blueprint $table) {
            try {
                $table->index('parent_id');
            } catch (Exception $e) {
            }
            try {
                $table->index('is_approved');
            } catch (Exception $e) {
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('comments')) {
            return;
        }

        Schema::table('comments', function (Blueprint $table) {
            try {
                $table->dropIndex(['parent_id']);
            } catch (Exception $e) {
            }
            try {
                $table->dropIndex(['is_approved']);
            } catch (Exception $e) {
            }
        });
    }
};
