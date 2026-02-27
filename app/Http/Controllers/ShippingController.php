<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
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

use App\Services\ShiprocketService;

class ShippingController extends Controller
{
    public function checkServiceability(Request $request)
    {
        $request->validate([
            'pickup_pincode'   => 'required',
            'delivery_pincode' => 'required',
            'weight'           => 'required|numeric',
            'cod'              => 'required|in:0,1',
            'length'           => 'nullable|numeric',
            'breadth'          => 'nullable|numeric',
            'height'           => 'nullable|numeric',
        ]);

        $token = $this->getShiprocketToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Shiprocket auth failed'
            ], 500);
        }

        // 📦 Build query
        $query = [
            'pickup_postcode'   => $request->pickup_pincode,
            'delivery_postcode' => $request->delivery_pincode,
            'weight'            => $request->weight,
            'cod'               => $request->cod,
        ];

        if ($request->length)  $query['length']  = $request->length;
        if ($request->breadth) $query['breadth'] = $request->breadth;
        if ($request->height)  $query['height']  = $request->height;

        // 🚀 API CALL
        $response = Http::withToken($token)
            ->get('https://apiv2.shiprocket.in/v1/external/courier/serviceability/', $query);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Shiprocket API failed',
                'error'   => $response->json()
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data'    => $response->json()
        ]);
    }

    public function shipBy(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'ship-by' => 'required|in:shiprocket,bluedart,delhivery,not_selected',
            'length' => 'nullable|numeric',
            'breadth' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
        ]);

        // ✅ FETCH SINGLE ORDER (NOT COLLECTION)
        $order = Orders::with([
            'user',
            'items.product',
            'items.variation',
            'shipping.address'
        ])->findOrFail($request->id);

        // ✅ CHECK SHIPPING EXISTS
        if (!$order->shipping) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping record not found for this order'
            ], 404);
        }

        // 🚀 SHIPROCKET CASE
        if ($request->input('ship-by') === 'shiprocket') {

            return $this->punchToShiprocketWithCurl(
                $order, // 👈 PASS FULL ORDER
                $request->only(['length', 'breadth', 'height', 'weight'])
            );
        }

        // 🟢 OTHER COURIER → JUST UPDATE SHIPPING TABLE
        $order->shipping->update([
            'shipping_by' => $request->input('ship-by')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipping method updated successfully'
        ]);
    }

    // Ship rocket Payload
    private function punchToShiprocketWithCurl($order, $dimensions = [])
    {
        $token = $this->getShiprocketToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to authenticate with Shiprocket.'
            ], 500);
        }

        // ✅ Get the User
        $fullName = $order->user->name ?? 'Unknown Name';
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        // ✅ Get Shipping Address
        $address = $order->shipping->address;
        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping address not found.'
            ], 404);
        }

        // ✅ Prepare Items Array
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                "name" => $item->product->name ?? 'Unknown Product',
                "sku" => $item->uid, // SKU from uid
                "units" => $item->quantity,
                // "selling_price" => $item->total,
                "selling_price" => $item->variation->sell_price ?? 0, // Price from product table
            ];
        }

        // ✅ Build Final Shiprocket Payload
        $orderData = [
            "order_id" => $order->order_code ?? ('ORD' . $order->id),
            "order_date" => now()->format('Y-m-d'),
            "pickup_location" => "work", // must match your pickup location in Shiprocket panel
            "billing_customer_name" => $firstName,
            "billing_last_name" => $lastName,
            "billing_address" => trim($address->address_line_1 . ' ' . $address->address_line_2),
            "billing_city" => $address->city,
            "billing_pincode" => $address->pincode,
            "billing_state" => $address->state,
            "billing_country" => $address->country,
            "billing_email" => $address->email,
            "billing_phone" => $address->mobile,
            "shipping_is_billing" => true,
            "order_items" => $items,
            "payment_method" => strtoupper($order->payment_type ?? 'COD'),
            "total_discount" => $order->coupon_discount ?? 0,
            "sub_total" => ($order->grand_total ?? 0) - ($order->shipping->shipping_charge?? 0),
            "shipping_charges" => $order->shipping->shipping_charge ?? 0,
            "length" => $dimensions['length'] ?? 10,
            "breadth" => $dimensions['breadth'] ?? 10,
            "height" => $dimensions['height'] ?? 10,
            "weight" => $dimensions['weight'] ?? 0.5,
        ];

        // ✅ Send to Shiprocket via cURL
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://apiv2.shiprocket.in/v1/external/orders/create/adhoc",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($orderData),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer $token"
            ],
             // adjust if needed
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return response()->json([
                'success' => false,
                'message' => 'cURL Error: ' . $error
            ], 500);
        }

        $decoded = json_decode($response, true);

        if (!empty($decoded['status_code']) && $decoded['status_code'] == 1) {

            if (!empty($decoded['shipment_id'])) {
                $order->shipping->update([
                    'shipping_by' => 'shiprocket',
                    'shipping_delivery_id' => $decoded['shipment_id'],
                    'response_' => json_encode($decoded)
                ]);
            }
            return response()->json([
                'success' => true,
                'message' => 'Shiprocket order punched',
                'shiprocket_response' => $decoded
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Shiprocket API failed',
            'shiprocket_response' => $decoded
        ], 400);
    }
    
    // Ship Rocket token
    private function getShiprocketToken()
    {
        $response = \Illuminate\Support\Facades\Http::timeout(20)
            ->post('https://apiv2.shiprocket.in/v1/external/auth/login', [
                'email' => env('SHIPROCKET_EMAIL'),
                'password' => env('SHIPROCKET_PASSWORD'),
            ]);

        if ($response->successful()) {
            return $response['token'];
        }

        \Log::error('Shiprocket auth failed', $response->json());
        return null;
    }

    // Ship Rocket All Orders (Dupli)
    public function getShiprocketOrders(Request $request)
    {
        $limit = $request->input('limit', 10);

        $orders = Orders::with(['user', 'items.product', 'shipping'])
            ->whereHas('shipping', function ($q) {
                $q->where('shipping_by', 'shiprocket');
            })
            ->latest()
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    // Ship Rocket All Orders With Filters
    public function fetchAllShiprocketOrders(Request $request)
    {
        $token = $this->getShiprocketToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to authenticate with Shiprocket'
            ], 500);
        }

        // Build query parameters dynamically
        $params = [];

        if ($request->has('filter_by') && $request->has('filter')) {
            $params['filter_by'] = $request->input('filter_by');
            $params['filter'] = $request->input('filter');
        }

        if ($request->has('from_date')) {
            $params['from_date'] = $request->input('from_date'); // YYYY-MM-DD
        }

        if ($request->has('to_date')) {
            $params['to_date'] = $request->input('to_date'); // YYYY-MM-DD
        }

        $params['per_page'] = $request->input('per_page', 20); // default 20
        $params['page'] = $request->input('page', 1); // default 1

        // Convert query array to URL string
        $query = http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://apiv2.shiprocket.in/v1/external/orders?$query",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                "Content-Type: application/json"
            ],
             // update if needed
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return response()->json([
                'success' => false,
                'message' => 'Shiprocket API Error: ' . $error
            ], 500);
        }

        $decoded = json_decode($response, true);

        return response()->json([
            'success' => true,
            'message' => 'Shiprocket orders fetched successfully',
            'data' => $decoded
        ]);
    }

    // Shipment Stats
    public function getMonthlyShippingStats()
    {
        $token = $this->getShiprocketToken();
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed with Shiprocket.'
            ], 500);
        }

        // Step 1: Initialize all months with null
        $monthlyOrders = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthName = \Carbon\Carbon::create()->month($i)->format('F');
            $monthlyOrders[$monthName] = 0;
        }

        $statusSummary = [
            'new' => 0,
            'cancelled' => 0,
            'delivered' => 0,
        ];

        $orders = $this->allShiprocketOrders($token);
        $totalOrders = count($orders);

        foreach ($orders as $order) {
            if (!isset($order['created_at'])) {
                continue;
            }

            try {
                $orderDate = \Carbon\Carbon::parse($order['created_at']);
                // if ($orderDate->year !== now()->year) {
                //     continue;
                // }

                $monthName = $orderDate->format('F');

                // ✅ Increment monthly count
                $monthlyOrders[$monthName] = ($monthlyOrders[$monthName] ?? 0) + 1;

                // ✅ Clean up status
                $status = strtolower($order['status'] ?? '');
                if (str_contains($status, 'delivered')) {
                    $statusSummary['delivered']++;
                } elseif (str_contains($status, 'cancel')) {
                    $statusSummary['cancelled']++;
                } else {
                    $statusSummary['new']++;
                }
            } catch (\Exception $e) {
                \Log::warning('Shiprocket order_date parse error', [
                    'order_id' => $order['order_id'] ?? null,
                    'order_date' => $order['order_date'] ?? null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'monthly_orders' => $monthlyOrders,
            'total_orders' => $totalOrders,
            'status_summary' => $statusSummary
        ]);
    }

    private function allShiprocketOrders($token)
    {
        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->get("https://apiv2.shiprocket.in/v1/external/orders?per_page=100");

        if (!$response->successful()) {
            \Log::error('Shiprocket order fetch failed', $response->json());
            return [];
        }

        return $response['data'] ?? [];
    }

    // Cancel Both Orders and shipping
    public function cancelShiprocketOrder(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $token = $this->getShiprocketToken();
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to authenticate with Shiprocket.'
            ], 500);
        }

        $order = Orders::with('shipping')->find($request->id);

        if (!$order || !$order->shipping || $order->shipping->shipping_by !== 'shiprocket' || !$order->shipping->shipping_delivery_id ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Shiprocket order or not yet shipped.'
            ], 404);
        }

        $shipmentId = $order->shipping->shipping_delivery_id;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://apiv2.shiprocket.in/v1/external/orders/cancel/shipment/{$shipmentId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                "Content-Type: application/json",
            ],
             // Update path if needed
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return response()->json([
                'success' => false,
                'message' => 'cURL Error: ' . $error,
            ], 500);
        }

        $decoded = json_decode($response, true);

        // ✅ Optionally mark as cancelled in local DB
        // $order->delivery_status = 'cancelled';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Shiprocket order cancelled successfully.',
            'response' => $decoded
        ]);
    }

    // Track Shipment
    public function trackShipment(Request $request)
    {
        $request->validate([
            'awb' => 'required_without:shipment_id|string',
            'shipment_id' => 'required_without:awb|numeric',
        ]);

        $token = $this->getShiprocketToken();
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to authenticate with Shiprocket'
            ], 500);
        }

        // Decide which endpoint to call
        if ($request->filled('awb')) {
            $url = "https://apiv2.shiprocket.in/v1/external/courier/track?awb=" . $request->awb;
        } else {
            $url = "https://apiv2.shiprocket.in/v1/external/courier/track/shipment/" . $request->shipment_id;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                "Content-Type: application/json"
            ],
            
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return response()->json([
                'success' => false,
                'message' => 'cURL Error: ' . $error
            ], 500);
        }

        $decoded = json_decode($response, true);

        // Optional: Auto-update local order status
        if (!empty($decoded['tracking_data']['shipment_status'])) {
            $shipmentId = $decoded['tracking_data']['shipment_id'] ?? null;
            if ($shipmentId) {
                $order = Orders::whereHas('shipping', function ($q) use ($shipmentId) {
                    $q->where('shipping_delivery_id', $shipmentId);
                })->first();
                if ($order) {
                    $order->delivery_status = strtolower($decoded['tracking_data']['shipment_status']);
                    $order->save();
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Tracking fetched successfully',
            'tracking' => $decoded
        ]);
    }
}
