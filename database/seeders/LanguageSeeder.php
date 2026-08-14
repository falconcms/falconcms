<?php

namespace FalconCms\Core\Database\Seeders;

use FalconCms\Core\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run()
    {
        Language::updateOrInsert(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'status' => true]);
    }
}
