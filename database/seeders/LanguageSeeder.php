<?php

namespace FalconCms\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use FalconCms\Core\Models\Language;

class LanguageSeeder extends Seeder
{
    public function run()
    {
        Language::updateOrInsert(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'status' => true]);
    }
}
