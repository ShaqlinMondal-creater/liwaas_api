<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Razorpay\Api\Api;
use App\Models\Orders;
use App\Models\Payments;
use Illuminate\Support\Facades\DB;


class PaymentController extends Controller
{
    // public function verify(Request $request)
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

    //         $api->utility->verifyPaymentSignature($attributes);

    //         DB::beginTransaction();

    //         // Fetch order
    //         $order = Orders::where('razorpay_order_id', $request->razorpay_order_id)->firstOrFail();

    //         // Save payment record
    //         $payment = Payments::create([
    //             'razorpay_payment_id' => $request->razorpay_payment_id,
    //             'razorpay_order_id' => $request->razorpay_order_id,
    //             'method' => 'razorpay',
    //             'amount' => $order->grand_total,
    //             'status' => 'paid',
    //             'order_id' => $order->id,
    //             'user_id' => $order->user_id,
    //         ]);

    //         // Update order payment status
    //         $order->payment_status = 'paid';
    //         $order->save();

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Payment verified and saved successfully',
    //             'payment_id' => $payment->id
    //         ]);
    //     } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Payment signature verification failed',
    //             'error' => $e->getMessage()
    //         ], 422);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Something went wrong',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function verify(Request $request)
    {
        $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
            'user_id' => 'required|exists:users,id',
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric'
        ]);

        try {
            $api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));

            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature
            ];

            $api->utility->verifyPaymentSignature($attributes);

            DB::beginTransaction();

            Payments::create([
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_order_id' => $request->razorpay_order_id,
                'method' => 'razorpay',
                'amount' => $request->amount,
                'status' => 'paid',
                'order_id' => $request->order_id,
                'user_id' => $request->user_id,
            ]);

            Orders::where('id', $request->order_id)->update([
                'payment_status' => 'paid'
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Payment recorded successfully.']);
        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
                'error' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
