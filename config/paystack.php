<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paystack Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Paystack payment integration.
    | You can get your API keys from https://dashboard.paystack.com/#/settings/developers
    |
    */

    'public_key' => env('PAYSTACK_PUBLIC_KEY', 'pk_test_...'),
    'secret_key' => env('PAYSTACK_SECRET_KEY', 'sk_test_...'),
    
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook settings for Paystack
    |
    */
    
    'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
    'webhook_url' => env('PAYSTACK_WEBHOOK_URL', env('APP_URL') . '/api/webhooks/paystack'),
    
    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Configure payment settings
    |
    */
    
    'currency' => 'GHS',
    'callback_url' => env('PAYSTACK_CALLBACK_URL', env('APP_URL') . '/payment/callback'),
    
    /*
    |--------------------------------------------------------------------------
    | Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure pricing for different services
    |
    */
    
    'pricing' => [
        'premium_listing' => 25000, // GH₵250 in kobo
        'bundle_package' => 60000,  // GH₵600 in kobo
        'hostel_booking' => 35000,  // GH₵350 in kobo
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Transfer Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic transfers to sellers
    |
    */
    
    'auto_transfer' => env('PAYSTACK_AUTO_TRANSFER', true),
    'transfer_fee_percentage' => env('PAYSTACK_TRANSFER_FEE', 1.5), // 1.5% platform fee
];
