<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('cms_settings')->insert([
            ['key' => 'login_url', 'value' => 'admin-login'],
            ['key' => 'register_url', 'value' => 'admin-register'],
            ['key' => 'login_theme', 'value' => 'classic'],
            ['key' => 'register_theme', 'value' => 'classic'],
            ['key' => 'users_can_register', 'value' => '1'],
            ['key' => 'timezone', 'value' => 'Asia/Dhaka'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_settings');
    }
};
