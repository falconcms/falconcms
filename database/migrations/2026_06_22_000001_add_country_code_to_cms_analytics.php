<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_analytics') && !Schema::hasColumn('cms_analytics', 'country_code')) {
            Schema::table('cms_analytics', function (Blueprint $table) {
                $table->string('country_code', 2)->nullable()->after('country');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cms_analytics', 'country_code')) {
            Schema::table('cms_analytics', function (Blueprint $table) {
                $table->dropColumn('country_code');
            });
        }
    }
};
