<?php

namespace Database\Seeders;

use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $testPassword = '123qaz!@#WSX';

        User::firstOrCreate(
            ['email' => env('PORTAL_ADMIN_EMAIL', 'admin@nt.vigourtech.net')],
            [
                'role' => 'admin',
                'name' => env('PORTAL_ADMIN_NAME', 'Portal Administrator'),
                'nida' => env('PORTAL_ADMIN_NIDA', '19000101000000000001'),
                'phone' => env('PORTAL_ADMIN_PHONE', '+255700000000'),
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password' => Hash::make((string) env('PORTAL_ADMIN_PASSWORD', 'password')),
            ],
        );

        $technicians = [
            [
                'name' => 'Evaristus Maduhu',
                'nida' => '19000101000000000006',
                'email' => 'evaristusmaduhu@gmail.com',
                'phone' => '+255700100004',
                'skills' => ['Electrical', 'Plumbing', 'Appliance repair'],
                'latitude' => -6.7924,
                'longitude' => 39.2083,
                'rating' => 5.0,
                'password' => $testPassword,
            ],
            [
                'name' => 'Asha Msuya',
                'nida' => '19000101000000000003',
                'email' => 'asha.tech@example.com',
                'phone' => '+255700100001',
                'skills' => ['Plumbing', 'Water pumps', 'Leak repair'],
                'latitude' => -6.7924,
                'longitude' => 39.2083,
                'rating' => 4.8,
            ],
            [
                'name' => 'Baraka Mwita',
                'nida' => '19000101000000000004',
                'email' => 'baraka.tech@example.com',
                'phone' => '+255700100002',
                'skills' => ['Electrical', 'Solar', 'Wiring'],
                'latitude' => -6.8161,
                'longitude' => 39.2803,
                'rating' => 4.7,
            ],
            [
                'name' => 'Neema John',
                'nida' => '19000101000000000005',
                'email' => 'neema.tech@example.com',
                'phone' => '+255700100003',
                'skills' => ['Appliance repair', 'Refrigeration', 'AC'],
                'latitude' => -6.7712,
                'longitude' => 39.2429,
                'rating' => 4.9,
            ],
        ];

        User::updateOrCreate(['email' => 'client@example.com'], [
            'role' => 'client',
            'name' => 'Demo Client',
            'nida' => '19000101000000000002',
            'phone' => '+255700200001',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'last_location' => ['latitude' => -6.8, 'longitude' => 39.25, 'updatedAt' => now()->toISOString()],
        ]);

        User::updateOrCreate(['email' => 'sample.client@vigourtech.net'], [
            'role' => 'client',
            'name' => 'Sample Client',
            'nida' => '19000101000000000007',
            'phone' => '+255700200002',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make($testPassword),
            'last_location' => ['latitude' => -6.8, 'longitude' => 39.25, 'updatedAt' => now()->toISOString()],
        ]);

        foreach ($technicians as $row) {
            $user = User::updateOrCreate(['email' => $row['email']], [
                'role' => 'technician',
                'name' => $row['name'],
                'nida' => $row['nida'],
                'phone' => $row['phone'],
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password' => Hash::make($row['password'] ?? 'password'),
                'last_location' => [
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'updatedAt' => now()->toISOString(),
                ],
            ]);

            Technician::updateOrCreate(['email' => $row['email']], [
                'user_id' => $user->id,
                'name' => $row['name'],
                'nida' => $row['nida'],
                'phone' => $row['phone'],
                'password' => Hash::make($row['password'] ?? 'password'),
                'skills' => $row['skills'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'available' => true,
                'client_requests_blocked' => false,
                'client_requests_blocked_reason' => null,
                'rating' => $row['rating'],
                'registration_review_status' => 'approved',
                'registration_review_note' => null,
                'registration_reviewed_at' => now(),
                'registration_payment_status' => 'paid',
                'last_seen_at' => now(),
            ]);
        }
    }
}
