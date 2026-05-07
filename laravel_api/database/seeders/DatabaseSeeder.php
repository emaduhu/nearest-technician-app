<?php

namespace Database\Seeders;

use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $technicians = [
            [
                'name' => 'Asha Msuya',
                'email' => 'asha.tech@example.com',
                'phone' => '+255700100001',
                'skills' => ['Plumbing', 'Water pumps', 'Leak repair'],
                'latitude' => -6.7924,
                'longitude' => 39.2083,
                'rating' => 4.8,
            ],
            [
                'name' => 'Baraka Mwita',
                'email' => 'baraka.tech@example.com',
                'phone' => '+255700100002',
                'skills' => ['Electrical', 'Solar', 'Wiring'],
                'latitude' => -6.8161,
                'longitude' => 39.2803,
                'rating' => 4.7,
            ],
            [
                'name' => 'Neema John',
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
            'phone' => '+255700200001',
            'password' => 'password',
            'last_location' => ['latitude' => -6.8, 'longitude' => 39.25, 'updatedAt' => now()->toISOString()],
        ]);

        foreach ($technicians as $row) {
            $user = User::updateOrCreate(['email' => $row['email']], [
                'role' => 'technician',
                'name' => $row['name'],
                'phone' => $row['phone'],
                'password' => 'password',
                'last_location' => [
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'updatedAt' => now()->toISOString(),
                ],
            ]);

            Technician::updateOrCreate(['email' => $row['email']], [
                'user_id' => $user->id,
                'name' => $row['name'],
                'phone' => $row['phone'],
                'password' => 'password',
                'skills' => $row['skills'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'available' => true,
                'rating' => $row['rating'],
                'last_seen_at' => now(),
            ]);
        }
    }
}
