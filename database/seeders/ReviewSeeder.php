<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Review;
use App\Models\User;
use App\Models\Item;
use App\Models\Order;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = Order::where('status', 'delivered')->get();

        foreach ($orders as $order) {
            // Create item review
            Review::create([
                'reviewer_id' => $order->buyer_id,
                'target_id' => $order->item_id,
                'order_id' => $order->id,
                'type' => 'item',
                'rating' => rand(3, 5),
                'title' => $this->getReviewTitle('item'),
                'comment' => $this->getReviewComment('item'),
                'is_helpful' => rand(0, 1),
                'status' => 'approved'
            ]);

            // Create seller review
            Review::create([
                'reviewer_id' => $order->buyer_id,
                'target_id' => $order->seller_id,
                'order_id' => $order->id,
                'type' => 'seller',
                'rating' => rand(3, 5),
                'title' => $this->getReviewTitle('seller'),
                'comment' => $this->getReviewComment('seller'),
                'is_helpful' => rand(0, 1),
                'status' => 'approved'
            ]);
        }
    }

    private function getReviewTitle($type)
    {
        $titles = [
            'item' => [
                'Great product!',
                'Exactly as described',
                'Good quality',
                'Fast delivery',
                'Highly recommended'
            ],
            'seller' => [
                'Excellent seller',
                'Very responsive',
                'Great communication',
                'Reliable seller',
                'Would buy again'
            ]
        ];

        return $titles[$type][array_rand($titles[$type])];
    }

    private function getReviewComment($type)
    {
        $comments = [
            'item' => [
                'The item was exactly as described and arrived in perfect condition. Very satisfied with the purchase!',
                'Great quality product at a reasonable price. Would definitely recommend to others.',
                'Fast shipping and excellent packaging. The item exceeded my expectations.',
                'Good value for money. The seller was very helpful and answered all my questions.',
                'Perfect condition and exactly what I needed. Very happy with this purchase.'
            ],
            'seller' => [
                'Very professional and easy to communicate with. Would definitely buy from again.',
                'Great seller with excellent communication. The transaction was smooth and hassle-free.',
                'Reliable and trustworthy seller. Delivered exactly what was promised.',
                'Outstanding service and very responsive to messages. Highly recommended!',
                'Excellent seller with great attention to detail. Very satisfied with the experience.'
            ]
        ];

        return $comments[$type][array_rand($comments[$type])];
    }
}
