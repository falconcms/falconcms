<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('blocked_ips')) return;

        Schema::table('blocked_ips', function (Blueprint $table) {
            if (!Schema::hasColumn('blocked_ips', 'city'))   $table->string('city')->nullable()->after('country_code');
            if (!Schema::hasColumn('blocked_ips', 'region')) $table->string('region')->nullable()->after('city');
            if (!Schema::hasColumn('blocked_ips', 'isp'))    $table->string('isp')->nullable()->after('region');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('blocked_ips')) return;

        Schema::table('blocked_ips', function (Blueprint $table) {
            foreach (['city', 'region', 'isp'] as $col) {
                if (Schema::hasColumn('blocked_ips', $col)) $table->dropColumn($col);
            }
        });
    }
};
