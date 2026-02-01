<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Staff;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User']
        );

        Company::factory()
            ->count(3)
            ->create()
            ->each(function (Company $company) {
                Staff::factory()->count(12)->create([
                    'company_id' => $company->id,
                ]);
            });
    }
}
