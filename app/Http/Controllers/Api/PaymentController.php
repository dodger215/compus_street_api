<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class PaymentController extends Controller
{
    private $paystackSecretKey;
    private $paystackPublicKey;

    public function __construct()
    {
        $this->paystackSecretKey = config('paystack.secret_key');
        $this->paystackPublicKey = config('paystack.public_key');
    }

    /**
     * Initialize payment
     */
    public function initialize(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100',
            'email' => 'required|email',
            'order_id' => 'nullable|exists:orders,id',
            'plan' => 'nullable|string',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $client = new Client();
            $response = $client->post('https://api.paystack.co/transaction/initialize', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->paystackSecretKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'amount' => $request->amount * 100, // Convert to kobo
                    'email' => $request->email,
                    'reference' => $this->generateReference(),
                    'callback_url' => config('app.url') . '/payment/callback',
                    'metadata' => [
                        'order_id' => $request->order_id,
                        'plan' => $request->plan,
                        'description' => $request->description,
                        'user_id' => $request->user()->id
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['status']) {
                // Create payment record
                $payment = Payment::create([
                    'user_id' => $request->user()->id,
                    'order_id' => $request->order_id,
                    'amount' => $request->amount,
                    'currency' => 'GHS',
                    'reference' => $data['data']['reference'],
                    'status' => 'pending',
                    'plan' => $request->plan,
                    'description' => $request->description,
                    'paystack_response' => $data
                ]);

                return response()->json([
                    'success' => true,
                    'authorization_url' => $data['data']['authorization_url'],
                    'access_code' => $data['data']['access_code'],
                    'reference' => $data['data']['reference'],
                    'payment_id' => $payment->id
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to initialize payment'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Payment initialization failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Payment initialization failed'
            ], 500);
        }
    }

    /**
     * Verify payment
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $client = new Client();
            $response = $client->get('https://api.paystack.co/transaction/verify/' . $request->reference, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->paystackSecretKey,
                    'Content-Type' => 'application/json'
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['status'] && $data['data']['status'] === 'success') {
                $payment = Payment::where('reference', $request->reference)->first();
                
                if ($payment) {
                    $payment->update([
                        'status' => 'success',
                        'paystack_response' => $data,
                        'paid_at' => now()
                    ]);

                    // Update order if exists
                    if ($payment->order_id) {
                        $order = Order::find($payment->order_id);
                        if ($order) {
                            $order->updatePaymentStatus('paid', $request->reference);
                        }
                    }

                    // Handle different payment types
                    $this->handlePaymentSuccess($payment, $data);

                    return response()->json([
                        'success' => true,
                        'payment' => $payment,
                        'message' => 'Payment verified successfully'
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Payment record not found'
                    ], 404);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Payment verification failed'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Payment verification failed'
            ], 500);
        }
    }

    /**
     * Handle Paystack webhook
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('X-Paystack-Signature');

        // Verify webhook signature
        if (!$this->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = $payload['event'];
        $data = $payload['data'];

        switch ($event) {
            case 'charge.success':
                $this->handleChargeSuccess($data);
                break;
            case 'charge.failed':
                $this->handleChargeFailed($data);
                break;
            case 'transfer.success':
                $this->handleTransferSuccess($data);
                break;
            case 'transfer.failed':
                $this->handleTransferFailed($data);
                break;
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Get payment details
     */
    public function show(Payment $payment)
    {
        return response()->json([
            'success' => true,
            'payment' => $payment
        ]);
    }

    /**
     * Get user payments
     */
    public function index(Request $request)
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'payments' => $payments
        ]);
    }

    /**
     * Generate payment reference
     */
    private function generateReference()
    {
        return 'CS_' . time() . '_' . rand(1000, 9999);
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature($payload, $signature)
    {
        $computedSignature = hash_hmac('sha512', json_encode($payload), config('paystack.secret_key'));
        return hash_equals($signature, $computedSignature);
    }

    /**
     * Handle payment success
     */
    private function handlePaymentSuccess($payment, $data)
    {
        switch ($payment->plan) {
            case 'premium':
                $this->handlePremiumPayment($payment);
                break;
            case 'bundle':
                $this->handleBundlePayment($payment);
                break;
            case 'hostel':
                $this->handleHostelPayment($payment);
                break;
            default:
                $this->handleOrderPayment($payment);
                break;
        }

        // Send email notification
        $this->sendPaymentNotification($payment, 'success');
    }

    /**
     * Handle premium payment
     */
    private function handlePremiumPayment($payment)
    {
        // Add premium credits to user
        $user = $payment->user;
        $stats = $user->stats ?? [];
        $stats['premium_credits'] = ($stats['premium_credits'] ?? 0) + 1;
        $user->update(['stats' => $stats]);
    }

    /**
     * Handle bundle payment
     */
    private function handleBundlePayment($payment)
    {
        // Add bundle credits to user
        $user = $payment->user;
        $stats = $user->stats ?? [];
        $stats['premium_credits'] = ($stats['premium_credits'] ?? 0) + 3;
        $user->update(['stats' => $stats]);
    }

    /**
     * Handle hostel payment
     */
    private function handleHostelPayment($payment)
    {
        // Create hostel booking
        // Implementation depends on hostel booking system
    }

    /**
     * Handle order payment
     */
    private function handleOrderPayment($payment)
    {
        if ($payment->order_id) {
            $order = Order::find($payment->order_id);
            if ($order) {
                $order->updatePaymentStatus('paid', $payment->reference);
            }
        }
    }

    /**
     * Handle charge success
     */
    private function handleChargeSuccess($data)
    {
        $payment = Payment::where('reference', $data['reference'])->first();
        if ($payment) {
            $payment->update([
                'status' => 'success',
                'paystack_response' => $data,
                'paid_at' => now()
            ]);
        }
    }

    /**
     * Handle charge failed
     */
    private function handleChargeFailed($data)
    {
        $payment = Payment::where('reference', $data['reference'])->first();
        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'paystack_response' => $data
            ]);
        }
    }

    /**
     * Handle transfer success
     */
    private function handleTransferSuccess($data)
    {
        // Handle successful transfer to seller
    }

    /**
     * Handle transfer failed
     */
    private function handleTransferFailed($data)
    {
        // Handle failed transfer to seller
    }

    /**
     * Send payment notification
     */
    private function sendPaymentNotification($payment, $status)
    {
        // Send email notification
        // Implementation depends on email service
    }
}
