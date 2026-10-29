<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Folders for the media library. They exist only here: a file stays where it was uploaded
 * (media/Y/m/...), so moving it between folders never changes its address and no page that
 * shows it breaks. A file in no folder (folder_id null) is "Uncategorized".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_media_folders')) {
            Schema::create('cms_media_folders', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('media') && !Schema::hasColumn('media', 'folder_id')) {
            Schema::table('media', function (Blueprint $table) {
                $table->unsignedBigInteger('folder_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('media', 'folder_id')) {
            Schema::table('media', function (Blueprint $table) {
                $table->dropIndex(['folder_id']);
                $table->dropColumn('folder_id');
            });
        }
        Schema::dropIfExists('cms_media_folders');
    }
};
