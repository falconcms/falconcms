<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $newAbout     = 'A clean, fast, and professional CMS. Built for readability and seamless content delivery.';
        $newCopyright = '© ' . date('Y') . ' All rights reserved by Falcon CMS';

        // Update only if still set to old Astra-inspired default
        DB::table('cms_settings')
            ->where('key', 'footer_about')
            ->where('value', 'LIKE', '%Astra-inspired%')
            ->update(['value' => $newAbout]);

        // Update only if still set to old "Your Site" default
        DB::table('cms_settings')
            ->where('key', 'theme_footer_copyright')
            ->where('value', 'LIKE', '%Your Site%')
            ->update(['value' => $newCopyright]);
    }

    public function down(): void {}
};
