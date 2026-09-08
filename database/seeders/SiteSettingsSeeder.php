<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::setValue('branding.logo_text', 'AJ Creative Studio');
        SiteSetting::setValue('branding.logo_path', null);
    }
}
