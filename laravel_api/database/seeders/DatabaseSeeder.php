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
        ];

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
