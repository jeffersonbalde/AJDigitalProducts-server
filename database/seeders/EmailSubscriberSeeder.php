<?php

namespace Database\Seeders;

use App\Models\EmailSubscriber;
use Illuminate\Database\Seeder;

class EmailSubscriberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $emails = [
            'hello@ajcreative.com',
            'team@ajcreative.com',
            'updates@ajcreative.com',
            'templates@ajcreative.com',
        ];

        foreach ($emails as $email) {
            EmailSubscriber::updateOrCreate(
                ['email' => $email],
                ['email' => $email]
            );
        }
    }
}
