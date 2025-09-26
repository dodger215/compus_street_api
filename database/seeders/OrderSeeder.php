<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\Item;
use App\Models\User;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = Item::where('status', 'available')->get();
        $users = User::where('is_admin', false)->get();

        $statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
        $paymentStatuses = ['pending', 'paid', 'failed'];

        for ($i = 0; $i < 50; $i++) {
            $item = $items->random();
            $buyer = $users->where('id', '!=', $item->seller_id)->random();
            
            $quantity = rand(1, 3);
            $totalAmount = $item->price * $quantity;

            Order::create([
                'buyer_id' => $buyer->id,
                'seller_id' => $item->seller_id,
                'item_id' => $item->id,
                'item_title' => $item->title,
                'item_price' => $item->price,
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
                'status' => $statuses[array_rand($statuses)],
                'payment_status' => $paymentStatuses[array_rand($paymentStatuses)],
                'payment_reference' => 'CS_' . time() . '_' . rand(1000, 9999),
                'shipping_address' => "Room {$i}, Hall {$i}, {$buyer->location}",
                'notes' => "Please deliver during office hours.",
                'timeline' => json_encode([
                    [
                        'status' => 'pending',
                        'timestamp' => now()->subDays(rand(1, 30))->toISOString(),
                        'note' => 'Order created'
                    ],
                    [
                        'status' => $statuses[array_rand($statuses)],
                        'timestamp' => now()->subDays(rand(1, 15))->toISOString(),
                        'note' => 'Order updated'
                    ]
                ])
            ]);
        }
    }
}
