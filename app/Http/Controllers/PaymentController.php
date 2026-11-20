<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Razorpay\Api\Api;
use App\Models\Orders;
use App\Models\OrderItems;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\ProductVariations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class PaymentController extends Controller
{
    // public function verifyPayment(Request $request)
    // {
    //     $request->validate([
    //         'razorpay_payment_id' => 'required|string',
    //         'razorpay_order_id' => 'required|string',
    //         'razorpay_signature' => 'required|string',
    //     ]);

    //     try {
    //         $api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));

    //         $attributes = [
    //             'razorpay_order_id' => $request->razorpay_order_id,
    //             'razorpay_payment_id' => $request->razorpay_payment_id,
    //             'razorpay_signature' => $request->razorpay_signature
    //         ];

    //         // 🔐 Verify Razorpay signature
    //         $api->utility->verifyPaymentSignature($attributes);

    //         DB::beginTransaction();

    //         // 🔍 Find existing payment created during order
    //         $payment = Payment::where('genarate_order_id', $request->razorpay_order_id)->first();

    //         if (!$payment) {
    //             return response()->json(['error' => 'Payment record not found'], 404);
    //         }

    //         // 🔄 Update payment record
    //         $payment->transaction_payment_id = $request->razorpay_payment_id;
    //         $payment->payment_status = 'success';
    //         $payment->response_ = json_encode($attributes);
    //         $payment->save();

    //         // 🔄 Update order
    //         if ($payment->order_id) {
    //             Orders::where('id', $payment->order_id)->update([
    //                 'order_status' => 'confirmed',
    //             ]);
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Payment verified successfully',
    //             'order_id' => $payment->order_id
    //         ]);

    //     } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Invalid Razorpay signature',
    //             'error' => $e->getMessage()
    //         ], 422);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Payment verification failed',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        try {

            $api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));

            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature
            ];

            // 🔐 Verify Razorpay signature
            $api->utility->verifyPaymentSignature($attributes);

            DB::beginTransaction();

            // 🔍 Find existing payment
            $payment = Payment::where('genarate_order_id', $request->razorpay_order_id)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment record not found'
                ], 404);
            }

            // 🔄 Update Payment
            $payment->transaction_payment_id = $request->razorpay_payment_id;
            $payment->payment_status = 'success';
            $payment->response_ = json_encode($attributes);
            $payment->save();

            // 🔄 Update Order Status + decrement stock once on first confirm
            $order = Orders::find($payment->order_id);

            if ($order) {
                $wasPending = $order->order_status === 'pending';

                $order->order_status = 'confirmed';
                $order->save();

                if ($wasPending) {
                    $orderItems = OrderItems::where('order_id', $order->id)->get();

                    foreach ($orderItems as $item) {
                        $quantity = (int) ($item->quantity ?? 0);
                        if (!$item->uid || $quantity < 1) {
                            continue;
                        }

                        $variation = ProductVariations::where('uid', $item->uid)->lockForUpdate()->first();

                        if (!$variation) {
                            throw new \Exception("Variation not found for UID: {$item->uid}");
                        }

                        $availableStock = (int) ($variation->stock ?? 0);

                        if ($availableStock < $quantity) {
                            throw new \Exception("Insufficient stock for UID: {$item->uid}");
                        }

                        $variation->stock = $availableStock - $quantity;
                        $variation->save();
                    }
                }
            }

            // 📝 Save Payment Log
            PaymentLog::create([
                'order_id' => $order?->id,
                'payment_id' => $payment->id,
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'status' => 'success',
                'request_payload' => json_encode($request->all()),
                'response_payload' => json_encode($attributes),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'order_id' => $payment->order_id
            ]);

        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {

            // ❌ Signature Failed
            PaymentLog::create([
                'razorpay_order_id' => $request->razorpay_order_id ?? null,
                'razorpay_payment_id' => $request->razorpay_payment_id ?? null,
                'status' => 'signature_error',
                'request_payload' => json_encode($request->all()),
                'response_payload' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid Razorpay signature',
            ], 422);

        } catch (\Exception $e) {

            DB::rollBack();

            // ❌ General Failure
            PaymentLog::create([
                'razorpay_order_id' => $request->razorpay_order_id ?? null,
                'razorpay_payment_id' => $request->razorpay_payment_id ?? null,
                'status' => 'failed',
                'request_payload' => json_encode($request->all()),
                'response_payload' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function autoUpdatePendingOrders()
    {
        try {

            DB::beginTransaction();

            /*
            🔵 1️⃣ After 5 minutes → payment becomes PROCESSING
            Order remains PENDING
            */
            $processingPayments = Payment::where('payment_status', 'pending')
                ->whereHas('order', function ($q) {
                    $q->where('order_status', 'pending')
                    ->where('payment_type', 'Prepaid')
                    ->whereBetween('created_at', [
                        now()->subMinutes(9),
                        now()->subMinutes(5)
                    ]);
                })
                ->get();

            foreach ($processingPayments as $payment) {
                $payment->payment_status = 'processing';
                $payment->save();
            }


            /*
            🔴 2️⃣ After 9 minutes → cancel everything
            */
            $cancelOrders = Orders::where('order_status', 'pending')
                ->where('payment_type', 'Prepaid')
                ->whereHas('payment', function ($q) {
                    $q->whereIn('payment_status', ['pending', 'processing']);
                })
                ->where('created_at', '<=', now()->subMinutes(9))
                ->get();

            foreach ($cancelOrders as $order) {

                // Cancel Order
                $order->order_status = 'cancelled';
                $order->save();

                // Cancel Payment
                if ($order->payment) {
                    $order->payment->payment_status = 'failed';
                    $order->payment->save();
                }

                // Cancel Shipping
                if ($order->shipping) {
                    $order->shipping->shipping_status = 'Cancelled';
                    $order->shipping->save();
                }

                // Log
                PaymentLog::create([
                    'order_id' => $order->id,
                    'payment_id' => $order->payment?->id,
                    'status' => 'auto_cancelled',
                    'request_payload' => null,
                    'response_payload' => 'Auto cancelled after 9 minutes timeout',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Auto update executed successfully.',
                'processing_payments' => $processingPayments->count(),
                'cancelled_orders' => $cancelOrders->count()
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Auto update failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancelPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required|string'
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $payment = Payment::where('genarate_order_id', $request->razorpay_order_id)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        $order = Orders::find($payment->order_id);
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        // Only the order owner can cancel this payment (blocks cancel-by-id guessing).
        $ownerId = (string) ($payment->user_id ?? $order->user_id);
        if ($ownerId === '' || $ownerId !== (string) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to cancel this payment.',
            ], 403);
        }

        $payment->payment_status = 'failed';
        $payment->save();

        $order->order_status = 'cancelled';
        $order->save();

        PaymentLog::create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'razorpay_order_id' => $request->razorpay_order_id,
            'status' => 'cancelled',
            'request_payload' => json_encode($request->all()),
            'response_payload' => 'User cancelled payment',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment cancelled successfully'
        ]);
    }

}
