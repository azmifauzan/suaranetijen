<?php

namespace Database\Seeders;

use App\Domains\Entities\Services\SeedEntityImporter;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(SeedEntityImporter $importer): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@suaranetijen.id'],
            [
                'name' => 'Admin SuaraNetijen',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $admin->forceFill(['is_admin' => true])->save();

        User::firstOrCreate(
            ['email' => 'user@suaranetijen.id'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $seedPath = database_path('data/seed_entities.csv');
        if (file_exists($seedPath)) {
            $importer->import($seedPath);
        }

        $this->call(SourceSeeder::class);
    }
}
