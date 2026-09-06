<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Plan::seedDefaultPlans();

        $this->call([
            AdminUserSeeder::class,
        ]);

        if (! app()->environment('production')) {
            $this->call(SampleOperatorCatalogSeeder::class);
        }
    }
}
