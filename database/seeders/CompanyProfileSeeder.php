<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\CompanyProfile;
use Illuminate\Database\Seeder;

class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profile = CompanyProfile::query()->firstOrCreate(
            ['tin' => '000-000-000-00000'],
            [
                'name' => 'OpenPRS Trading Corp.',
                'mobile_no' => '(+63917) 000-0000',
                'email' => null,
                'currency' => 'PESO',
            ],
        );

        $addresses = [
            [
                'recipient_name' => 'OpenPRS Trading Corp. - Head Office',
                'street' => '100 Commerce Avenue, Metro Business Park',
                'barangay' => 'San Lorenzo',
                'city' => 'Makati City',
                'province' => 'Metro Manila',
                'zip_code' => '1200',
                'mobile_no' => '(+63917) 000-0000',
            ],
            [
                'recipient_name' => 'OpenPRS Trading Corp. - South Plant',
                'street' => 'Block 2, Lot 8, Southgate Industrial Estate',
                'barangay' => 'Balibago',
                'city' => 'Santa Rosa',
                'province' => 'Laguna',
                'zip_code' => '4026',
                'mobile_no' => '(+63917) 000-0001',
            ],
            [
                'recipient_name' => 'OpenPRS Trading Corp. - Port Warehouse',
                'street' => '45 Harbor Drive',
                'barangay' => 'Barangay 650',
                'city' => 'Manila',
                'province' => 'Metro Manila',
                'zip_code' => '1012',
                'mobile_no' => '(+63917) 000-0002',
            ],
        ];

        foreach ($addresses as $addressData) {
            Address::query()->firstOrCreate(
                ['recipient_name' => $addressData['recipient_name']],
                array_merge($addressData, ['company_profile_id' => $profile->id])
            );
        }
    }
}
