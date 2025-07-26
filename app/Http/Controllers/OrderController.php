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

class OrderController extends Controller
{

    public function createOrder(Request $request)  // Create order
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'shipping_id' => 'required|integer|exists:addresses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.aid' => 'required|string',
            'items.*.uid' => 'required|exists:product_variations,uid',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            // Step 1: Calculate subtotal (excluding tax)
            $subTotal = 0;
            foreach ($request->items as $item) {
                $variation = ProductVariations::where('uid', $item['uid'])->firstOrFail();
                $totalPrice = $variation->sell_price * $item['quantity'];
                $subTotal += $totalPrice;
            }

            // Step 2: Calculate tax (18% of subtotal)
            $tax = round($subTotal * 0.18, 2);

            // Step 3: Determine shipping charge
            $shippingCharge = $subTotal > 1000 ? 0 : 80;

            // Step 4: Calculate grand total
            // $grandTotal = round($subTotal + $tax + $shippingCharge, 2);
            $grandTotal = round($subTotal + $shippingCharge, 2);


            // Step 5: Create order
            $order = Orders::create([
                'user_id' => $request->user_id,
                'order_code' => $this->generateOrderCode(),
                'invoice_no' => null,
                'invoice_link' => null,
                'shipping' => 'Pending',
                'shipping_type' => 'home delivery',
                'shipping_by' => 'not_select',
                'shipping_id' => $request->shipping_id, // <-- use the ID directly
                'shipping_charge' => $shippingCharge,
                'tax_price' => $tax,
                'grand_total' => $grandTotal,
                'payment_type' => $request->payment_type, // ✅ corrected
                'payment_status' => 'pending',
                'razorpay_order_id' => null,
                'delivery_status' => 'pending',
                'coupon_id' => null,
                'track_code' => null,
            ]);

            // Step 5.1: Create Razorpay Order only if payment_type is 'prepaid'
            if ($request->payment_type === 'prepaid') {
                $api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));

                $razorpayOrder = $api->order->create([
                    'receipt' => $order->order_code,
                    'amount' => $grandTotal * 100, // in paise
                    'currency' => 'INR',
                    'payment_capture' => 1
                ]);

                $order->razorpay_order_id = $razorpayOrder['id'];
                $order->save();
            }


            // Step 6: Add order items
            foreach ($request->items as $item) {
                $variation = ProductVariations::where('uid', $item['uid'])->firstOrFail();
                $total = $variation->sell_price * $item['quantity'];
                $itemTax = round($total * 0.18, 2);

                OrderItems::create([
                    'order_id' => $order->id,
                    'user_id' => $request->user_id,
                    'product_id' => $item['product_id'],
                    'aid' => $item['aid'],
                    'uid' => $item['uid'],
                    'quantity' => $item['quantity'],
                    'total' => $total,
                    'tax' => $itemTax,
                ]);
            }

            $user = User::find($request->user_id);

            // use for sent email
            // if ($user && $user->email) {
            //     $order->load('items'); // if you need order items in email
            //     Mail::to($user->email)->send(new OrderPlacedMail($order));
            // }

            DB::commit();

            return response()->json([
                'message' => 'Order successfully created',
                'order_code' => $order->order_code,
                'order_id' => $order->id,
                'razorpay_order_id' => $order->razorpay_order_id,
                // 'razorpay_key' => config('services.razorpay.key'),
                'amount' => $grandTotal * 100, // in paise
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Order creation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

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

    public function updateOrderStatus(Request $request, $id)
    {
        $request->validate([
            'shipping' => 'nullable|string|in:Pending,Approved,Completed',
            'delivery_status' => 'nullable|string|in:pending,completed,shipped,Near You',
        ]);

        $order = Orders::findOrFail($id);

        // Update order status
        $order->shipping = $request->shipping ?? 'Pending';
        $order->delivery_status = $request->delivery_status ?? 'pending';

        // Generate invoice_no if necessary
        if ($order->shipping !== 'Pending' && empty($order->invoice_no)) {
            $invoice_no = $this->generateInvoiceNo($order);
            $order->invoice_no = $invoice_no;
        }

        $order->save();

        // Add image_link to each item
        foreach ($order->items as $item) {
            $item->image_link = $this->getImageLinkForItem($item);
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

        // Ensure invoices directory exists
        $this->ensureDirectoryExists('invoices');

        // Save invoice link first
        $randomName = Str::random(24) . '.pdf';
        $pdfUrl = url('invoices/' . $randomName);
        $order->invoice_link = $pdfUrl;

        // Generate QR code BEFORE PDF
        $qrImageName = $this->generateQRCode($pdfUrl);
        $order->track_code = $qrImageName;
        $order->save(); // save both invoice_link and track_code

        // Now generate PDF AFTER QR is available
        $pdfPath = public_path('invoices/' . $randomName);
        Pdf::loadView('pdf.invoice', ['order' => $order])->save($pdfPath);

        // ✅ Send status update email
        if ($order->user && !empty($order->user->email)) {
            Mail::to($order->user->email)->send(new OrderStatusUpdated($order));
        }

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order,
            'invoice_link' => $order->invoice_link,
            'track_code' => $order->track_code,
        ]);
    }

    // Update Status Helper functions
    private function generateInvoiceNo($order)
    {
        $date = now()->format('Y/m/d');
        $prefix = 'INV-' . $date . '/';
        $countToday = Orders::whereDate('created_at', now())
            ->whereNotNull('invoice_no')
            ->count();

        $baseNumber = 101 + $countToday;
        $baseInvoiceNo = $prefix . $baseNumber;

        // Ensure uniqueness
        $invoice_no = $baseInvoiceNo;
        if (Orders::where('invoice_no', 'LIKE', $baseInvoiceNo . '%')->exists()) {
            $suffixNumber = 1;
            do {
                $invoice_no = $baseInvoiceNo . 'D' . $suffixNumber;
                $exists = Orders::where('invoice_no', $invoice_no)->exists();
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
            return url($upload->path);
        }

        $defaultPath = 'logos/default_liwaas.png';
        return file_exists(public_path($defaultPath)) ? url($defaultPath) : null;
    }
    private function ensureDirectoryExists($dir)
    {
        $path = public_path($dir);
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }
    private function generateQRCode($invoiceLink)
    {
        $qrImageName = 'qr_' . Str::random(24) . '.png';
        $qrImagePath = public_path('qr/' . $qrImageName);

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
                                $imageUrl = $upload->url;
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

        $query = Orders::with(['items.variation', 'items.product'])
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
                'invoice_no' => $order->invoice_no,
                'invoice_link' => $order->invoice_link,
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
                                $imageUrl = $upload->url;
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

}

