<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate([
            'name' => 'Admin User',
            'email' => 'admin@campusstreet.com',
            'password' => Hash::make('admin123'),
            'year' => 4,
            'phone' => '+233123456789',
            'bio' => 'Campus Street Administrator',
            'location' => 'Accra, Ghana',
            'college_domain' => 'gctu.edu.gh',
            'is_verified' => true,
            'is_admin' => true,
            'preferences' => json_encode([
                'notifications' => true,
                'email_updates' => true,
                'sms_updates' => false
            ]),
            'stats' => json_encode([
                'listings_count' => 0,
                'orders_count' => 0,
                'reviews_count' => 0,
                'rating' => 0
            ])
        ]);

        // Create sample users
        $colleges = ['gctu.edu.gh', 'knust.edu.gh', 'ug.edu.gh', 'ashesi.edu.gh'];
        $years = [1, 2, 3, 4, 5, 6];
        $locations = ['Accra', 'Kumasi', 'Cape Coast', 'Tamale', 'Takoradi'];

        for ($i = 1; $i <= 20; $i++) {
            User::create([
                'name' => "Student {$i}",
                'email' => "student{$i}@" . $colleges[array_rand($colleges)],
                'password' => Hash::make('password123'),
                'year' => $years[array_rand($years)],
                'phone' => '+233' . rand(200000000, 999999999),
                'bio' => "I'm a student looking to buy and sell items on campus.",
                'location' => $locations[array_rand($locations)],
                'college_domain' => $colleges[array_rand($colleges)],
                'is_verified' => rand(0, 1),
                'preferences' => json_encode([
                    'notifications' => true,
                    'email_updates' => rand(0, 1),
                    'sms_updates' => rand(0, 1)
                ]),
                'stats' => json_encode([
                    'listings_count' => rand(0, 10),
                    'orders_count' => rand(0, 15),
                    'reviews_count' => rand(0, 8),
                    'rating' => rand(35, 50) / 10
                ])
            ]);
        }
    }
}
