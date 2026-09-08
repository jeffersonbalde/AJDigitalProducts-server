<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            // PersonnelSeeder::class,
            // ProductCategorySeeder::class,
            // ProductCollectionSeeder::class,
            SiteSettingsSeeder::class,
            LandingPageSectionSeeder::class,
            // ProductSeeder::class,
            // EmailSubscriberSeeder::class,
            // ProductReviewSeeder::class,
            // ContactMessageSeeder::class,
            ProductFaqSeeder::class,
        ]);
    }
}
