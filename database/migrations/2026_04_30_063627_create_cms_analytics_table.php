<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cms_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->nullable();
            $table->text('url')->nullable();
            $table->text('referrer')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('device_type')->nullable(); // desktop, mobile, tablet
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cms_analytics');
    }
};
