<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\User;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'academic_books' => [
                'Mathematics for Engineers',
                'Physics Fundamentals',
                'Chemistry Lab Manual',
                'Engineering Design',
                'Computer Science Basics',
                'Economics Principles',
                'Business Management',
                'Literature Studies'
            ],
            'electronics' => [
                'Laptop',
                'Smartphone',
                'Tablet',
                'Headphones',
                'Calculator',
                'USB Cable',
                'Power Bank',
                'Bluetooth Speaker'
            ],
            'stationery' => [
                'Notebooks',
                'Pens and Pencils',
                'Rulers',
                'Calculators',
                'Folders',
                'Highlighters',
                'Staplers',
                'Paper Clips'
            ],
            'transportation' => [
                'Bicycle',
                'Scooter',
                'Motorcycle',
                'Car',
                'Bus Pass',
                'Taxi Voucher'
            ],
            'furniture' => [
                'Study Desk',
                'Office Chair',
                'Bookshelf',
                'Bed Frame',
                'Dining Table',
                'Sofa'
            ],
            'clothing' => [
                'T-Shirts',
                'Jeans',
                'Dresses',
                'Shoes',
                'Jackets',
                'Formal Wear'
            ],
            'sports' => [
                'Football',
                'Basketball',
                'Tennis Racket',
                'Running Shoes',
                'Gym Equipment',
                'Swimming Gear'
            ]
        ];

        $conditions = ['new', 'like_new', 'good', 'fair', 'poor'];
        $statuses = ['available', 'sold', 'pending'];
        $locations = ['Accra', 'Kumasi', 'Cape Coast', 'Tamale', 'Takoradi'];

        $users = User::where('is_admin', false)->get();

        foreach ($categories as $category => $items) {
            foreach ($items as $itemName) {
                $user = $users->random();
                
                Item::create([
                    'title' => $itemName,
                    'description' => "High quality {$itemName} in excellent condition. Perfect for students. Contact for more details.",
                    'category' => $category,
                    'subcategory' => $this->getSubcategory($category),
                    'price' => rand(10, 500),
                    'condition' => $conditions[array_rand($conditions)],
                    'location' => $locations[array_rand($locations)],
                    'images' => json_encode([
                        'https://via.placeholder.com/400x300?text=' . urlencode($itemName),
                        'https://via.placeholder.com/400x300?text=Image+2',
                        'https://via.placeholder.com/400x300?text=Image+3'
                    ]),
                    'is_premium' => rand(0, 1),
                    'is_available' => true,
                    'views_count' => rand(0, 100),
                    'status' => $statuses[array_rand($statuses)],
                    'seller_id' => $user->id
                ]);
            }
        }
    }

    private function getSubcategory($category)
    {
        $subcategories = [
            'academic_books' => ['Textbooks', 'Reference Books', 'Lab Manuals'],
            'electronics' => ['Computers', 'Mobile Devices', 'Accessories'],
            'stationery' => ['Writing Tools', 'Office Supplies', 'Art Supplies'],
            'transportation' => ['Bicycles', 'Motorcycles', 'Cars'],
            'furniture' => ['Study Furniture', 'Living Room', 'Bedroom'],
            'clothing' => ['Casual Wear', 'Formal Wear', 'Sports Wear'],
            'sports' => ['Team Sports', 'Individual Sports', 'Fitness Equipment']
        ];

        return $subcategories[$category][array_rand($subcategories[$category])];
    }
}
