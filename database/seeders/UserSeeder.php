<?php

namespace Database\Seeders;

use App\Models\Signature;
use App\Models\User;
use App\Support\AttachmentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/users.json');
        $users = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $emails = array_column($users, 'email');
        $duplicates = array_keys(array_filter(array_count_values($emails), fn (int $count) => $count > 1));

        if ($duplicates !== []) {
            throw new \InvalidArgumentException(
                'users.json contains duplicate emails: '.implode(', ', $duplicates)
                .'. Each email must appear exactly once with all roles merged into a single entry.',
            );
        }

        $hasDepartmentColumn = Schema::hasColumn('users', 'department_code');

        $signatureSamples = [
            'sample-signature.png',
            'sample-signature-2.png',
            'sample-signature-3.png',
            'sample-signature-4.png',
        ];

        foreach ($users as $index => $entry) {
            $email = $entry['email'];
            $name = trim($entry['f_name'].' '.$entry['l_name']);
            $departmentCode = $entry['department_code'];
            $roles = $entry['roles'] ?? [];

            $attributes = [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
            ];

            if ($hasDepartmentColumn) {
                $attributes['department_code'] = $departmentCode;
            }

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                $attributes
            );

            if (! empty($roles)) {
                $user->syncRoles($roles);
            }

            // Seed signature if user doesn't have one
            if ($user->signatures()->count() === 0) {
                $sampleFile = $signatureSamples[$index % count($signatureSamples)];
                $samplePath = database_path('seeders/data/signature-sample/'.$sampleFile);

                if (File::exists($samplePath)) {
                    $signature = $user->signatures()->create([
                        'name' => 'Default Signature',
                        'is_active' => true,
                    ]);

                    $signature->addMedia($samplePath)
                        ->preservingOriginal()
                        ->toMediaCollection(AttachmentCollection::NAME);
                }
            }
        }
    }
}
