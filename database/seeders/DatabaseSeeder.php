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
            RolePermissionSeeder::class,
            WorkflowSeeder::class,
            CompanyProfileSeeder::class,
            UserSeeder::class,
            DepartmentSeeder::class,
            LineItemUnitSeeder::class,
            SupplierSeeder::class,
            DocumentSeeder::class,
            ProcurementWorkflowSeeder::class,
            PaymentRequestFormSeeder::class,
        ]);
    }
}
