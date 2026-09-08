<?php

namespace Database\Seeders;

use App\Models\Personnel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PersonnelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Personnel::create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'password' => Hash::make('password123'),
            'position' => 'Content Manager',
            'is_active' => true,
        ]);

        Personnel::create([
            'name' => 'Jane Smith',
            'username' => 'janesmith',
            'password' => Hash::make('password123'),
            'position' => 'Designer',
            'is_active' => true,
        ]);

        Personnel::create([
            'name' => 'Mike Johnson',
            'username' => 'mikejohnson',
            'password' => Hash::make('password123'),
            'position' => 'Developer',
            'is_active' => true,
        ]);
    }
}
