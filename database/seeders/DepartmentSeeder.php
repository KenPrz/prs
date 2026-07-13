<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('departments') || ! Schema::hasTable('user_departments')) {
            $this->command?->warn('Skipping DepartmentSeeder: departments or user_departments table does not exist.');

            return;
        }

        $departmentPath = database_path('seeders/data/departments.json');
        $departments = json_decode(File::get($departmentPath), true, 512, JSON_THROW_ON_ERROR);
        $departmentMap = [];

        foreach ($departments as $department) {
            $record = Department::query()->updateOrCreate(
                ['code' => $department['code']],
                ['name' => $department['name']]
            );

            $departmentMap[$record->code] = $record->id;
        }

        $userPath = database_path('seeders/data/users.json');
        $users = json_decode(File::get($userPath), true, 512, JSON_THROW_ON_ERROR);

        // The first member synced into each department becomes its head.
        $departmentHeads = [];

        foreach ($users as $entry) {
            $departmentCode = $entry['department_code'] ?? null;
            $email = $entry['email'] ?? null;

            if (! $departmentCode || ! $email || ! isset($departmentMap[$departmentCode])) {
                continue;
            }

            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                continue;
            }

            $user->departments()->syncWithoutDetaching([$departmentMap[$departmentCode]]);

            $departmentHeads[$departmentCode] ??= $user->id;
        }

        foreach ($departmentHeads as $departmentCode => $userId) {
            Department::query()
                ->where('id', $departmentMap[$departmentCode])
                ->update(['department_head_id' => $userId]);
        }
    }
}
