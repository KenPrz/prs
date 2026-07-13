<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production-safe seeding: reference data only — roles/permissions, workflow
 * templates, document templates, company profile, and item units. No demo
 * users, no demo documents (those live in DatabaseSeeder for dev).
 *
 * Usage: php artisan db:seed --force --class=Database\\Seeders\\ReferenceDataSeeder
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            WorkflowSeeder::class,
            CompanyProfileSeeder::class,
            LineItemUnitSeeder::class,
            DocumentSeeder::class,
        ]);
    }
}
