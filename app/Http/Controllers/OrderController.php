<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Orders;
use App\Models\OrderItems;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariations;
use App\Models\Upload;
use App\Models\Counter;
use App\Models\AddressModel;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Shipping;
use App\Models\Payment;
use App\Models\Invoices;

use Razorpay\Api\Api;
use Illuminate\Support\Facades\File;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf; // <- This one often causes issues if not properly imported
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdated;
// use App\Services\ShiprocketService;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{

    // Create Order
    public function createOrder(Request $request)
    {
        $request->validate([
            'shipping_address_id' => 'required|exists:addresses,id',
            'payment_type' => 'required|in:COD,Prepaid',
            'coupon_key' => 'nullable|string'
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DB::beginTransaction();

        try {
            $userId = $user->id;

            $cartItems = Cart::with(['variation', 'product'])
                ->where('user_id', $userId)
                ->get();

            if ($cartItems->isEmpty()) {
                return response()->json(['error' => 'Cart is empty'], 400);
            }

            $subTotal = 0;
            foreach ($cartItems as $item) {
                $variation = $item->variation;
                if (!$variation) {
                    return response()->json(['error' => "Invalid variation for item UID: {$item->uid}"], 400);
                }

                $totalPrice = $variation->sell_price * $item->quantity;
                $subTotal += $totalPrice;
            }

            $tax = round($subTotal * 0.18, 2);
            $shippingCharge = $subTotal > 1000 ? 0 : 80;

            $discount = 0;
            $couponId = null;
            if ($request->coupon_key) {
                $coupon = Coupon::where('key_name', $request->coupon_key)
                    ->where('status', 'active')
                    ->first();

                if ($coupon) {
                    $discount = $coupon->value;
                    $couponId = $coupon->id;
                }
            }

            $grandTotal = round($subTotal + $shippingCharge - $discount, 2);

            // 🔸 Create Shipping
            $shipping = Shipping::create([
                'shipping_status' => 'Pending',
                'shipping_type' => 'Home',
                'shipping_by' => 'not_select',
                'address_id' => $request->shipping_address_id,
                'shipping_charge' => $shippingCharge,
            ]);

            $razorpayOrderId = null;

            // 🔸 If Prepaid, create Razorpay Order
            if (strtolower($request->payment_type) === 'prepaid') {
                $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));
                $razorpayOrder = $api->order->create([
                    'receipt' => 'rcpt_' . Str::random(10),
                    'amount' => $grandTotal * 100, // amount in paise
                    'currency' => 'INR'
                ]);
                $razorpayOrderId = $razorpayOrder['id'];
            }

            // 🔸 Create Payment
            $payment = Payment::create([
                'payment_type' => $request->payment_type,
                'payment_amount' => $grandTotal,
                'payment_status' => 'pending',
                'user_id' => $userId,
                'genarate_order_id' => $razorpayOrderId,
            ]);

            // 🔸 Create Order
            $order = Orders::create([
                'user_id' => $userId,
                'order_code' => $this->generateOrderCode(),
                'invoice_id' => null,
                'shipping_id' => $shipping->id,
                'tax_price' => $tax,
                'grand_total' => $grandTotal,
                'payment_type' => $request->payment_type,
                'payment_id' => $payment->id,
                'delivery_status' => 'pending',
                'coupon_id' => $couponId,
                'coupon_discount' => $discount,
            ]);

            $payment->order_id = $order->id;
            $payment->save();

            // 🔸 Create Order Items
            foreach ($cartItems as $item) {
                $variation = $item->variation;
                $total = $variation->sell_price * $item->quantity;
                $itemTax = round($total * 0.18, 2);

                OrderItems::create([
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'product_id' => $item->products_id,
                    'aid' => $item->aid,
                    'uid' => $item->uid,
                    'quantity' => $item->quantity,
                    'total' => $total,
                    'tax' => $itemTax,
                ]);
            }

            // 🔸 Create Invoice
            $invoice = Invoices::create([
                'invoice_no' => 'INV-' . strtoupper(Str::random(6)),
                'invoice_link' => null,
                'invoice_qr' => null,
            ]);

            $order->invoice_id = $invoice->id;
            $order->save();

            // 🔸 Send Email
            if ($user->email) {
                $order->load('items');
                Mail::to($user->email)->send(new \App\Mail\OrderPlacedMail($order));
            }

            // 🔸 Clear Cart
            // Cart::where('user_id', $userId)->delete();

            DB::commit();

            $response = [
                'message' => 'Order successfully created',
                'order_code' => $order->order_code,
                'order_id' => $order->id,
                'amount' => $grandTotal,
            ];

            if ($razorpayOrderId) {
                $response['razorpay_order_id'] = $razorpayOrderId;
                $response['currency'] = 'INR';
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Order creation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Make payment confirmation -> now we ignore this function
    // public function handlePaymentCallback(Request $request)
    // {
    //     $request->validate([
    //         'razorpay_order_id' => 'required|string',
    //         'razorpay_payment_id' => 'nullable|string',
    //         'status' => 'required|in:success,failed,cancelled',
    //         'response' => 'required|array', // entire Razorpay or gateway response
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         // 🔍 Find payment by Razorpay order ID
    //         $payment = Payment::where('genarate_order_id', $request->razorpay_order_id)->first();

    //         if (!$payment) {
    //             return response()->json(['error' => 'Payment not found'], 404);
    //         }

    //         // 🔄 Update payment status and gateway info
    //         $payment->transaction_payment_id = $request->razorpay_payment_id ?? null;
    //         $payment->payment_status = $request->status;
    //         $payment->response_ = json_encode($request->response);
    //         $payment->save();

    //         // 🔄 Optionally update order delivery status
    //         // if ($payment->order_id) {
    //         //     $order = Orders::find($payment->order_id);
    //         //     if ($order) {
    //         //         if ($request->status === 'success') {
    //         //             $order->delivery_status = 'confirmed';
    //         //         } elseif ($request->status === 'failed') {
    //         //             $order->delivery_status = 'payment_failed';
    //         //         } elseif ($request->status === 'cancelled') {
    //         //             $order->delivery_status = 'cancelled';
    //         //         }
    //         //         $order->save();
    //         //     }
    //         // }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Payment status updated successfully',
    //             'payment_status' => $payment->payment_status,
    //             'order_id' => $payment->order_id,
    //         ]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'error' => 'Payment update failed',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    // Response like :
    // {
    //     "razorpay_order_id": "order_LXy0df123abc",
    //     "razorpay_payment_id": "pay_LXy987xyz",
    //     "status": "success",
    //         "response": {
    //             "id": "pay_LXy987xyz",
    //             "entity": "payment",
    //             "amount": 19000,
    //             "currency": "INR",
    //             "status": "captured",
    //             "order_id": "order_LXy0df123abc",
    //             "method": "upi",
    //             "email": "user@example.com",
    //             ...
    //         }
    // }

    protected function generateOrderCode(): string
    {
        $prefix = now()->format('Ydm'); // Example: 20252606
        $counterName = 'order';

        return DB::transaction(function () use ($prefix, $counterName) {
            $counter = Counter::where('name', $counterName)->lockForUpdate()->first();

            // If counter doesn't exist, create it
            if (!$counter) {
                $counter = Counter::create([
                    'name' => $counterName,
                    'prefix' => $prefix,
                    'postfix' => 1,
                ]);
            }

            // If prefix is today’s prefix → continue incrementing
            // Else reset the prefix and postfix
            if ($counter->prefix !== $prefix) {
                $counter->prefix = $prefix;
                $counter->postfix = 1;
            }

            // Generate order code from prefix + postfix
            $orderCode = $counter->prefix . str_pad($counter->postfix, 4, '0', STR_PAD_LEFT);

            // Update counter for next time
            $counter->postfix += 1;
            $counter->save();

            return $orderCode;
        });
    }

    // Get Customer Order
    public function getMyOrders(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $limit = (int) $request->input('limit', 15);
        $offset = (int) $request->input('offset', 0);

        $query = Orders::with(['items.variation', 'items.product', 'invoice'])
            ->where('user_id', $user->id);

        $total = $query->count();

        $orders = $query->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get();

        $data = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'invoice_no' => $order->invoice ? $order->invoice->invoice_no : null,
                'invoice_link' => $order->invoice ? $order->invoice->invoice_link : null,
                'shipping' => $order->shipping,
                'payment_type' => $order->payment_type,
                'payment_status' => $order->payment_status,
                'delivery_status' => $order->delivery_status,
                'grand_total' => $order->grand_total,
                'items' => $order->items->map(function ($item) {
                    $imageId = null;
                    $imageUrl = null;

                    if ($item->variation && $item->variation->images_id) {
                        $imageIds = explode(',', $item->variation->images_id);
                        $firstId = trim($imageIds[0] ?? '');
                        if ($firstId) {
                            $upload = Upload::find($firstId);
                            if ($upload) {
                                $imageId = $upload->id;
                                $imageUrl = Storage::url($upload->path);
                            }
                        }
                    }

                    return [
                        'id' => $item->id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'image' => [
                                'upload_id' => $imageId,
                                'upload_url' => $imageUrl,
                            ]
                        ],
                        'variation' => $item->variation ? [
                            'uid' => $item->variation->uid,
                            'color' => $item->variation->color,
                            'size' => $item->variation->size,
                            'sell_price' => $item->variation->sell_price,
                        ] : null,
                        'quantity' => $item->quantity,
                        'total' => $item->total,
                        'tax' => $item->tax,
                    ];
                }),
                'created_at' => Carbon::parse($order->created_at)
                    ->timezone('Asia/Kolkata')
                    ->translatedFormat('jS M Y, h.iA'),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Your orders fetched successfully.',
            'total' => $total,
            'data' => $data
        ]);
    }

    // Update Status For Admin
    public function updateOrderStatus(Request $request, $id)
    {
        $request->validate([
            'shipping' => 'nullable|string|in:Pending,Approved,Completed',
            'delivery_status' => 'nullable|string|in:pending,delivered,arrived,shipped,Near You,cancel',
        ]);

        // Get the order
        $order = Orders::with('items')->findOrFail($id);

        // ✅ 1. Update shipping status in t_shipping
        if (!empty($request->shipping) && $order->shipping_id) {
            $shipping = Shipping::find($order->shipping_id);
            if ($shipping) {
                $shipping->shipping_status = $request->shipping;
                $shipping->save();
            }
        }

        // ✅ 2. Update delivery_status in orders table
        if (!empty($request->delivery_status)) {
            $order->delivery_status = $request->delivery_status;
        }

        // ✅ 3. Generate invoice and save in t_invoice if shipping is not Pending and invoice not already created
        if ($request->shipping !== 'Pending' && empty($order->invoice_id)) {

            $invoiceNo = $this->generateInvoiceNo($order);

            // Ensure directory exists
            $this->ensureDirectoryExists('invoices');
            $this->ensureDirectoryExists('qr');

            $pdfName = Str::random(24) . '.pdf';
            $pdfUrl = Storage::url('invoices/' . $pdfName);

            // Generate QR code
            $qrImageName = $this->generateQRCode($pdfUrl);

            // Save invoice to t_invoice table
            $invoice = Invoices::create([
                'invoice_no' => $invoiceNo,
                'invoice_link' => $pdfUrl,
                'invoice_qr' => $qrImageName,
                'date' => now(),
            ]);

            // Update order with new invoice_id
            $order->invoice_id = $invoice->id;

            // Generate PDF and save
            $pdfPath = storage_path('app/public/invoices/' . $pdfName);
            Pdf::loadView('pdf.invoice', ['order' => $order])->save($pdfPath);
        }

        $order->save();

        // ✅ 4. Attach image, color, and size info to each item
        foreach ($order->items as $item) {
            $variation = \App\Models\ProductVariations::where('uid', $item->uid)->first();
            if ($variation) {
                $item->image_link = $this->getImageLinkForItem($item);
                $item->color = $variation->color ?? null;
                $item->size = $variation->size ?? null;
            } else {
                $item->image_link = null;
                $item->color = null;
                $item->size = null;
            }
        }

        // ✅ 5. Send status update email to user
        if ($order->user && !empty($order->user->email)) {
            Mail::to($order->user->email)->send(new OrderStatusUpdated($order));
        }

        // return response()->json([
        //     'message' => 'Order updated successfully',
        //     'order' => $order,
        //     'invoice' => $order->invoice ? [
        //         'invoice_no'   => $order->invoice->invoice_no,
        //         'invoice_link' => $order->invoice->invoice_link,
        //         'invoice_qr'   => url('qr/' . $order->invoice->invoice_qr),
        //         'date'         => $order->invoice->date,
        //     ] : null,
        //     'shipping_address' => $order->shipping && $order->shipping->address
        //         ? $order->shipping->address
        //         : null,
        // ]);

        // Response Structure:
        return response()->json([
            'message' => 'Order updated successfully',
            'order' => [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'order_code' => $order->order_code,
                'invoice_id' => $order->invoice_id,
                'shipping_id' => $order->shipping_id,
                'tax_price' => $order->tax_price,
                'grand_total' => $order->grand_total,
                'payment_type' => $order->payment_type,
                'payment_id' => $order->payment_id,
                'delivery_status' => $order->delivery_status,
                'coupon_id' => $order->coupon_id,
                'coupon_discount' => $order->coupon_discount,
                'other_text' => $order->other_text,

                // Items
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'user_id' => $item->user_id,
                        'product_id' => $item->product_id,
                        'aid' => $item->aid,
                        'uid' => $item->uid,
                        'quantity' => $item->quantity,
                        'total' => $item->total,
                        'tax' => $item->tax,
                        'image_link' => $item->image_link,
                        // 'image_link' => basename($item->image_link),
                        'color' => $item->color,
                        'size' => $item->size,
                        'product' => [
                            'id' => $item->product->id,
                            'aid' => $item->product->aid,
                            'name' => $item->product->name
                        ]
                    ];
                }),

                // User
                'user' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'mobile' => $order->user->mobile,
                    'is_active' => $order->user->is_active,
                    'is_logged_in' => $order->user->is_logged_in,
                    'is_deleted' => $order->user->is_deleted
                ],

                // Shipping
                'shipping' => [
                    'id' => $order->shipping->id,
                    'shipping_status' => $order->shipping->shipping_status,
                    'shipping_by' => $order->shipping->shipping_by,
                    'shipping_type' => $order->shipping->shipping_type,
                    'address_id' => $order->shipping->address_id,
                    'shipping_charge' => $order->shipping->shipping_charge,
                    'shipping_delivery_id' => $order->shipping->shipping_delivery_id,
                    'response_' => $order->shipping->response_,
                    'address' => [
                        'id' => $order->shipping->address->id,
                        'user_id' => $order->shipping->address->user_id,
                        'name' => $order->shipping->address->name,
                        'email' => $order->shipping->address->email,
                        'address_type' => $order->shipping->address->address_type,
                        'mobile' => $order->shipping->address->mobile,
                        'state' => $order->shipping->address->state,
                        'city' => $order->shipping->address->city,
                        'country' => $order->shipping->address->country,
                        'pincode' => $order->shipping->address->pincode,
                        'address_line_1' => $order->shipping->address->address_line_1,
                        'address_line_2' => $order->shipping->address->address_line_2
                    ]
                ],

                // Invoice
                'invoice' => [
                    'id' => $order->invoice->id,
                    'invoice_no' => $order->invoice->invoice_no,
                    'invoice_link' => $order->invoice->invoice_link,
                    'invoice_qr' => $order->invoice->invoice_qr,
                    // 'invoice_qr' => url('qr/' . $order->invoice->invoice_qr),
                    'date' => $order->invoice->date
                ]
            ]
        ]);

    }

    public function deleteOrder($id)  // Delete order
    {
        $order = Orders::find($id);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Delete related order items first (if using foreign key with cascade, optional)
        $order->items()->delete();

        // Delete the order
        $order->delete();

        return response()->json([
            'status' => true,
            'message' => 'Order and related items deleted successfully'
        ], 200);
    }

    // Update Status Helper functions
    private function generateInvoiceNo($order)
    {
        $date = now()->format('Y/m/d');
        $prefix = 'INV-' . $date . '/';

        // ✅ Count today's invoices from the t_invoice table
        $countToday = \App\Models\Invoices::whereDate('created_at', now())
            ->whereNotNull('invoice_no')
            ->count();

        $baseNumber = 101 + $countToday;
        $baseInvoiceNo = $prefix . $baseNumber;

        // ✅ Ensure uniqueness from t_invoice table
        $invoice_no = $baseInvoiceNo;
        if (\App\Models\Invoices::where('invoice_no', $invoice_no)->exists()) {
            $suffixNumber = 1;
            do {
                $invoice_no = $baseInvoiceNo . 'D' . $suffixNumber;
                $exists = \App\Models\Invoices::where('invoice_no', $invoice_no)->exists();
                $suffixNumber++;
            } while ($exists);
        }

        return $invoice_no;
    }
    private function getImageLinkForItem($item)
    {
        $variation = \App\Models\ProductVariations::where('uid', $item->uid)->first();
        $imageId = $variation && !empty($variation->images_id) ? trim(explode(',', $variation->images_id)[0]) : 1;

        $upload = \App\Models\Upload::find($imageId);

        if ($upload && !empty($upload->path)) {
            return Storage::url($upload->path);
        }

        $defaultPath = 'logos/default_liwaas.png';
        return Storage::exists($defaultPath)
            ? Storage::url($defaultPath)
            : null;
    }
    private function ensureDirectoryExists(string $dir): void
    {
        Storage::disk('public')->makeDirectory($dir);
    }
    private function generateQRCode($invoiceLink)
    {
        $qrImageName = 'qr_' . Str::random(24) . '.png';
        $qrImagePath = storage_path('app/public/qr/' . $qrImageName);

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($invoiceLink)
            ->size(200)
            ->margin(10)
            ->build();

        // Save the QR code PNG to the file
        file_put_contents($qrImagePath, $result->getString());

        return $qrImageName;
    }

    // Get All order
    public function getAllOrders(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        // Filters
        $orderCode = $request->input('order_code');
        $userName = $request->input('user_name');
        $orderId = $request->input('order_id');

        $limit = (int) $request->input('limit', 15);
        $offset = (int) $request->input('offset', 0);

        $query = Orders::with(['user', 'items.variation', 'items.product'])
            ->when($orderCode, fn($q) => $q->where('order_code', 'like', "%$orderCode%"))
            ->when($orderId, fn($q) => $q->where('id', $orderId))
            ->when($userName, function ($q) use ($userName) {
                $q->whereHas('user', fn($q2) => $q2->where('name', 'like', "%$userName%"));
            });

        $total = $query->count();

        $orders = $query
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get();

        $data = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'invoice_no' => $order->invoice_no,
                'invoice_link' => $order->invoice_link,
                'user' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ],
                'shipping' => $order->shipping,
                'payment_type' => $order->payment_type,
                'payment_status' => $order->payment_status,
                'delivery_status' => $order->delivery_status,
                'grand_total' => $order->grand_total,
                'items' => $order->items->map(function ($item) {
                    // 🔍 Handle image from variation
                    $imageId = null;
                    $imageUrl = null;

                    if ($item->variation && $item->variation->images_id) {
                        $imageIds = explode(',', $item->variation->images_id);
                        $firstId = trim($imageIds[0] ?? '');
                        if ($firstId) {
                            $upload = Upload::find($firstId);
                            if ($upload) {
                                $imageId = $upload->id;
                                $imageUrl = Storage::url($upload->path);
                            }
                        }
                    }

                    return [
                        'id' => $item->id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'image' => [
                                'upload_id' => $imageId,
                                'upload_url' => $imageUrl,
                            ]
                        ],
                        'variation' => $item->variation ? [
                            'uid' => $item->variation->uid,
                            'color' => $item->variation->color,
                            'size' => $item->variation->size,
                            'sell_price' => $item->variation->sell_price,
                        ] : null,
                        'quantity' => $item->quantity,
                        'total' => $item->total,
                        'tax' => $item->tax,
                    ];
                }),
                'created_at' => Carbon::parse($order->created_at)
                    ->timezone('Asia/Kolkata')
                    ->translatedFormat('jS M Y, h.iA'),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'All orders fetched successfully.',
            'total' => $total,
            'data' => $data
        ]);
    }

}

