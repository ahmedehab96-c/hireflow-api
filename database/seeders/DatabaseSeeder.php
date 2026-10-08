<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Demo data only. Every seeder is idempotent, so `db:seed` can be re-run safely. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The demo accounts share a published password: never create them in production by accident.
        // A public demo deployment can opt in explicitly with SEED_DEMO_DATA=true.
        if (app()->isProduction() && ! filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Skipping demo data in production. Set SEED_DEMO_DATA=true to seed a public demo.');

            return;
        }

        $this->call([
            UserSeeder::class,
            SkillSeeder::class,
            CompanyJobSeeder::class,
            ApplicationSeeder::class,
        ]);
    }
}
