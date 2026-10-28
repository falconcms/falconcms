<?php

use FalconAi\Lifecycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Falcon AI's table and its one-time setup. The plugin is on by default, so on most sites its
 * activate() never runs: this migration (falcon:update runs it) does that work instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        // What each AI request used: counts only, never the prompt or the answer.
        if (!Schema::hasTable('falcon_ai_usage')) {
            Schema::create('falcon_ai_usage', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('provider', 40);
                $table->string('model', 100);
                $table->string('feature', 40);
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        if (class_exists(Lifecycle::class)) {
            Lifecycle::ensurePermission();
        }

        // AI writing used to live in Falcon SEO with its own Claude key and model: carry them
        // over (still encrypted with the same app key), so nobody has to paste the key again.
        if (Schema::hasTable('cms_settings')) {
            $old = DB::table('cms_settings')->whereIn('key', ['seo_ai_api_key', 'seo_ai_model'])->pluck('value', 'key');
            $carry = ['seo_ai_api_key' => 'ai_claude_key', 'seo_ai_model' => 'ai_claude_model'];
            foreach ($carry as $from => $to) {
                if (!empty($old[$from]) && !DB::table('cms_settings')->where('key', $to)->where('value', '!=', '')->exists()) {
                    DB::table('cms_settings')->updateOrInsert(['key' => $to], ['value' => $old[$from], 'updated_at' => now()]);
                }
            }
            DB::table('cms_settings')->whereIn('key', array_keys($carry))->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('falcon_ai_usage');
    }
};
