<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Models\StocksProduct;
use App\Models\StocksSalesOrder;
use App\Models\StocksSalesOrderItem;
use App\Models\StocksClient;
use App\Models\StocksUpload;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    // public function analyticsDashboard()
    // {

    //     // monthly targets
    //     $targets = [
    //         3 => 100,
    //         4 => 150,
    //         5 => 250,
    //         6 => 300,
    //         7 => 400,
    //         8 => 350,
    //         9 => 300,
    //         10 => 200,
    //         11 => 200,
    //         12 => 150
    //     ];

    //     $year = 2026;

    //     // TOTAL DATA
    //     $total_sales = StocksSalesOrder::sum('grand_total');
    //     $total_orders = StocksSalesOrder::count();
    //     $total_tax = StocksSalesOrder::sum('total_tax');
    //     $total_products = StocksProduct::count();
    //     $low_stock_products = StocksProduct::where('stock','<',10)->count();

    //     // CURRENT MONTH
    //     $month = date('n');

    //     $monthly_orders = StocksSalesOrder::whereYear('created_at',$year)
    //         ->whereMonth('created_at',$month)
    //         ->count();

    //     $target = $targets[$month] ?? 0;

    //     $remaining = max($target - $monthly_orders,0);

    //     $progress = $target > 0
    //         ? round(($monthly_orders/$target)*100,2)
    //         : 0;

    //     // MONTH WISE DATA
    //     $monthNames = [
    //         3=>"march",4=>"april",5=>"may",6=>"june",7=>"july",
    //         8=>"august",9=>"september",10=>"october",11=>"november",12=>"december"
    //     ];

    //     $monthWise = [];

    //     foreach($monthNames as $m=>$name){

    //         $orders = StocksSalesOrder::whereYear('created_at',$year)
    //             ->whereMonth('created_at',$m)
    //             ->count();

    //         $revenue = StocksSalesOrder::whereYear('created_at',$year)
    //             ->whereMonth('created_at',$m)
    //             ->sum('grand_total');

    //         $monthWise[] = [
    //             $name => [
    //                 "target" => $targets[$m] ?? 0,
    //                 "orders" => $orders,
    //                 "revenue" => $revenue
    //             ]
    //         ];
    //     }

    //     // TOP SELLING PRODUCTS
    //     $topProducts = StocksSalesOrderItem::select(
    //         'stocks_products.name as product_name',
    //         DB::raw('SUM(qty) as units_sold'),
    //         DB::raw('SUM(sub_total) as revenue')
    //     )
    //     ->join('stocks_products','stocks_products.uid','=','stocks_sales_order_items.uid')
    //     ->groupBy('stocks_products.name')
    //     ->orderByDesc('units_sold')
    //     ->limit(3)
    //     ->get();

    //     // SALES BY CLIENT
    //     $clientSales = StocksSalesOrder::select(
    //         'stocks_clients.name as client_name',
    //         DB::raw('SUM(grand_total) as total_sales'),
    //         DB::raw('COUNT(*) as orders')
    //     )
    //     ->join('stocks_clients','stocks_clients.id','=','stocks_sales_orders.client_id')
    //     ->groupBy('stocks_clients.name')
    //     ->orderByDesc('total_sales')
    //     ->limit(3)
    //     ->get();

    //     return response()->json([
    //         "status"=>true,
    //         "message"=>"Analytics fetched successfully",
    //         "data"=>[
    //             "total_data"=>[
    //                 "total_sales"=>$total_sales,
    //                 "total_orders"=>$total_orders,
    //                 "total_tax"=>$total_tax,
    //                 "total_products"=>$total_products,
    //                 "low_stock_products"=>$low_stock_products
    //             ],

    //             "this_month_data"=>[
    //                 "monthly_target"=>$target,
    //                 "monthly_orders"=>$monthly_orders,
    //                 "target_remaining"=>$remaining,
    //                 "target_progress_percent"=>$progress
    //             ],

    //             "month_wise_data"=>$monthWise,

    //             "top_selling_products"=>$topProducts,

    //             "sales_by_clients"=>$clientSales
    //         ]
    //     ]);
    // }

    public function analyticsDashboard()
    {

        // monthly targets
        $targets = [
            3 => 100,
            4 => 150,
            5 => 250,
            6 => 300,
            7 => 400,
            8 => 350,
            9 => 300,
            10 => 200,
            11 => 200,
            12 => 150
        ];

        $year = 2026;

        // TOTAL DATA
        $total_sales = StocksSalesOrder::sum('grand_total');
        $total_orders = StocksSalesOrder::count();
        $total_items_sold = StocksSalesOrderItem::sum('qty'); // NEW
        $total_tax = StocksSalesOrder::sum('total_tax');
        $total_products = StocksProduct::count();
        $low_stock_products = StocksProduct::where('stock','<',5)->count(); // updated to 5

        // CURRENT MONTH
        $month = date('n');

        $monthly_orders = StocksSalesOrder::whereYear('created_at',$year)
                ->whereMonth('created_at',$month)
                ->count();

            $monthly_items_sold = StocksSalesOrderItem::join(
            'stocks_sales_orders',
            'stocks_sales_orders.id',
            '=',
            'stocks_sales_order_items.sales_order_id'
        )
        ->whereYear('stocks_sales_orders.created_at',$year)
        ->whereMonth('stocks_sales_orders.created_at',$month)
        ->sum('qty');

        $target = $targets[$month] ?? 0;

        $remaining = max($target - $monthly_orders,0);

        $progress = $target > 0
            ? round(($monthly_orders/$target)*100,2)
            : 0;

        // MONTH WISE DATA
        $monthNames = [
            3=>"march",4=>"april",5=>"may",6=>"june",7=>"july",
            8=>"august",9=>"september",10=>"october",11=>"november",12=>"december"
        ];

        $monthWise = [];

        foreach($monthNames as $m=>$name){

            $orders = StocksSalesOrder::whereYear('created_at',$year)
                ->whereMonth('created_at',$m)
                ->count();
            $items_sold = StocksSalesOrderItem::join(
                'stocks_sales_orders',
                'stocks_sales_orders.id',
                '=',
                'stocks_sales_order_items.sales_order_id'
            )
            ->whereYear('stocks_sales_orders.created_at',$year)
            ->whereMonth('stocks_sales_orders.created_at',$m)
            ->sum('qty');
            $revenue = StocksSalesOrder::whereYear('created_at',$year)
                ->whereMonth('created_at',$m)
                ->sum('grand_total');

            $monthWise[] = [
                $name => [
                    "target" => $targets[$m] ?? 0,
                    "orders" => $orders,
                    "items_sold" => $items_sold,
                    "revenue" => $revenue
                ]
            ];
        }

        // TOP SELLING PRODUCTS
        $topProducts = StocksSalesOrderItem::select(
            'stocks_products.name as product_name',
            DB::raw('SUM(qty) as units_sold'),
            DB::raw('SUM(sub_total) as revenue')
        )
        ->join('stocks_products','stocks_products.uid','=','stocks_sales_order_items.uid')
        ->groupBy('stocks_products.name')
        ->orderByDesc('units_sold')
        ->limit(3)
        ->get();

        // SALES BY CLIENT
        $clientSales = StocksSalesOrder::select(
            'stocks_clients.name as client_name',
            DB::raw('SUM(grand_total) as total_sales'),
            DB::raw('COUNT(*) as orders')
        )
        ->join('stocks_clients','stocks_clients.id','=','stocks_sales_orders.client_id')
        ->groupBy('stocks_clients.name')
        ->orderByDesc('total_sales')
        ->limit(3)
        ->get();

        return response()->json([
            "status"=>true,
            "message"=>"Analytics fetched successfully",
            "data"=>[
                "total_data"=>[
                    "total_sales"=>$total_sales,
                    "total_orders"=>$total_orders,
                    "total_tax"=>$total_tax,
                    "total_products"=>$total_products,
                    "low_stock_products"=>$low_stock_products
                ],

                "this_month_data"=>[
                    "monthly_target"=>$target,
                    "monthly_orders"=>$monthly_orders,
                    "target_remaining"=>$remaining,
                    "target_progress_percent"=>$progress
                ],

                "month_wise_data"=>$monthWise,

                "top_selling_products"=>$topProducts,

                "sales_by_clients"=>$clientSales
            ]
        ]);
    }

    public function addProductStock(Request $request)
    {

        $request->validate([
            'name' => 'required|string',
            'size' => 'required|string',
            'color' => 'required|string',
            'list_price' => 'required|numeric',
            'sale_price' => 'required|numeric',
            'stock' => 'required|integer'
        ]);

        $last = StocksProduct::orderBy('uid','desc')->first();

        $uid = time();

        $product = StocksProduct::create([
            'uid' => $uid,
            'name' => $request->name,
            'size' => $request->size,
            'color' => $request->color,
            'list_price' => $request->list_price,
            'sale_price' => $request->sale_price,
            'stock' => $request->stock,
            'status' => $request->status ?? 1
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Stock product added successfully',
            'data' => $product
        ]);
    }
    public function deleteStock(Request $request)
    {

        $request->validate([
            'ids' => 'required|array'
        ]);

        StocksProduct::whereIn('id', $request->ids)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Stock deleted successfully'
        ]);

    }
    public function editStock(Request $request)
    {

        $request->validate([
            'id' => 'required|exists:stocks_products,id'
        ]);

        $product = StocksProduct::find($request->id);

        if ($request->has('name')) {
            $product->name = $request->name;
        }

        if ($request->has('size')) {
            $product->size = $request->size;
        }

        if ($request->has('color')) {
            $product->color = $request->color;
        }

        if ($request->has('list_price')) {
            $product->list_price = $request->list_price;
        }

        if ($request->has('sale_price')) {
            $product->sale_price = $request->sale_price;
        }

        if ($request->has('stock')) {
            $product->stock = $request->stock;
        }

        if ($request->has('status')) {
            $product->status = $request->status;
        }

        $product->save();

        return response()->json([
            'status' => true,
            'message' => 'Stock updated successfully',
            'data' => $product
        ]);
    }
    public function getProductStocks(Request $request)
    {

        $limit = $request->limit ?? 10;
        $offset = $request->offset ?? 0;

        $query = StocksProduct::query();

        // search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name','like','%'.$request->search.'%')
                ->orWhere('uid','like','%'.$request->search.'%');
            });
        }

        // size filter
        if ($request->filled('size')) {
            $query->where('size', $request->size);
        }

        // color filter
        if ($request->filled('color')) {
            $query->where('color', $request->color);
        }

        // status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // total count
        $total = $query->count();

        // fetch records
        $products = $query
            ->orderBy('id','desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Stocks fetched successfully',
            'total' => $total,
            'limit' => (int)$limit,
            'offset' => (int)$offset,
            'data' => $products
        ]);
    }


    // sales order functions
    public function createSalesOrder(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:stocks_clients,id',
            'items' => 'required|array|min:1',
            'items.*.uid' => 'required',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.tax' => 'nullable|numeric',
            // 'paid_amount' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            $grand_total = 0;
            $total_tax = 0;

            // confirm client exists
            $client = StocksClient::find($request->client_id);

            // generate order number
            $sales_order_no = 'SO' . time();

            $order = StocksSalesOrder::create([
                'sales_order_no' => $sales_order_no,
                'client_id' => $client->id,
                'grand_total' => 0,
                'total_tax' => 0,

                // ✅ NEW FIELDS
                'so_date' => now()->format('Y-m-d'), // store in DB format
                'status' => 'pending',
                'payment_status' => 'pending',
                'remain_due' => 0
            ]);

            foreach ($request->items as $item) {

                $price = $item['price'];
                $qty = $item['qty'];
                $tax_percent = $item['tax'] ?? 5;

                // ✅ TAX INCLUDED calculation
                $taxable_price = $price / (1 + ($tax_percent / 100));
                $tax_amount = $price - $taxable_price;

                // subtotal
                $sub_total = $price * $qty;

                // tax for quantity
                $sub_total_tax = $tax_amount * $qty;

                // accumulate totals
                $grand_total += $sub_total;
                $total_tax += $sub_total_tax;

                StocksSalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'uid' => $item['uid'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'tax' => $tax_percent,
                    'sub_total' => $sub_total,
                    'sub_total_tax' => $sub_total_tax
                ]);

                // reduce stock
                StocksProduct::where('uid', $item['uid'])
                    ->decrement('stock', $item['qty']);
            }

            $final_total = $grand_total + $total_tax;

            // round to nearest integer
            $rounded_total = round($final_total);

            // calculate round amount (correct)
            $round_amount = round($rounded_total - $final_total, 2);

            // ✅ NOW round values for storing
            $grand_total = round($grand_total, 2);
            $total_tax = round($total_tax, 2);

            $remain_due = $rounded_total;
            $payment_status = 'pending';

            $order->update([
                'grand_total' => $rounded_total,
                'total_tax' => $total_tax,
                'round_amount' => $round_amount,

                'remain_due' => $remain_due,
                'payment_status' => $payment_status
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Sales order created successfully',
                'data' => [
                    'sales_order_id' => $order->id,
                    'sales_order_no' => $order->sales_order_no,
                    'client' => [
                        'id' => $client->id,
                        'name' => $client->name,
                        'mobile' => $client->mobile
                    ],
                    'grand_total' => $rounded_total,
                    'total_tax' => $total_tax,
                    'round_amount' => $round_amount,
                ]
            ]);

        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    public function updateSalesOrder(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'nullable|exists:stocks_clients,id',
            'items' => 'nullable|array|min:1',
            'items.*.uid' => 'required_with:items',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'items.*.price' => 'required_with:items|numeric',
            'items.*.tax' => 'nullable|numeric',
            'paid_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pending,completed,on process',
            'payment_status' => 'nullable|in:pending,partial payment,completed',
            'so_date' => 'nullable|date'
        ]);

        DB::beginTransaction();

        try {

            $order = StocksSalesOrder::findOrFail($id);

            $grand_total = $order->grand_total;
            $total_tax = $order->total_tax;
            $rounded_total = $order->grand_total;

            // ✅ 1. If items passed → update items + stock
            if ($request->has('items')) {

                // restore old stock
                $oldItems = StocksSalesOrderItem::where('sales_order_id', $order->id)->get();

                foreach ($oldItems as $item) {
                    StocksProduct::where('uid', $item->uid)
                        ->increment('stock', $item->qty);
                }

                // delete old items
                StocksSalesOrderItem::where('sales_order_id', $order->id)->delete();

                $grand_total = 0;
                $total_tax = 0;

                foreach ($request->items as $item) {

                    $price = $item['price'];
                    $qty = $item['qty'];
                    $tax_percent = $item['tax'] ?? 5;

                    $taxable_price = $price / (1 + ($tax_percent / 100));
                    $tax_amount = $price - $taxable_price;

                    $sub_total = $price * $qty;
                    $sub_total_tax = $tax_amount * $qty;

                    $grand_total += $sub_total;
                    $total_tax += $sub_total_tax;

                    StocksSalesOrderItem::create([
                        'sales_order_id' => $order->id,
                        'uid' => $item['uid'],
                        'qty' => $qty,
                        'price' => $price,
                        'tax' => $tax_percent,
                        'sub_total' => $sub_total,
                        'sub_total_tax' => $sub_total_tax
                    ]);

                    // reduce stock
                    StocksProduct::where('uid', $item['uid'])
                        ->decrement('stock', $qty);
                }

                $grand_total = round($grand_total, 2);
                $total_tax = round($total_tax, 2);
                $rounded_total = round($grand_total);
                $round_amount = round($rounded_total - $grand_total, 2);

                $order->update([
                    'grand_total' => $rounded_total,
                    'total_tax' => $total_tax,
                    'round_amount' => $round_amount
                ]);
            }

            // ✅ 2. Payment logic (only if passed)
            if ($request->has('paid_amount')) {

                $paid_amount = $request->paid_amount;

                $current_due = $order->remain_due;

                if ($paid_amount > $current_due) {
                    throw new \Exception('Paid amount cannot be greater than remaining due');
                }

                $remain_due = $current_due - $paid_amount;

                // ✅ FIXED LOGIC
                if ($remain_due == 0) {
                    $payment_status = 'completed';
                } elseif ($remain_due < $rounded_total) {
                    $payment_status = 'partial payment';
                } else {
                    $payment_status = 'pending';
                }

                $orderUpdateData = [
                    'remain_due' => $remain_due,
                    'payment_status' => $payment_status
                ];

                // 🔥 auto complete order if fully paid
                if ($remain_due == 0) {
                    $orderUpdateData['status'] = 'completed';
                }

                $order->update($orderUpdateData);
            }

            // ✅ 3. Other fields update (only if passed)
            $updateData = [];

            if ($request->has('client_id')) {
                $updateData['client_id'] = $request->client_id;
            }

            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            if ($request->has('payment_status')) {
                $updateData['payment_status'] = $request->payment_status;
            }

            if ($request->has('so_date')) {
                $updateData['so_date'] = $request->so_date;
            }

            if (!empty($updateData)) {
                $order->update($updateData);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Sales order updated successfully'
            ]);

        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    public function getSalesOrders(Request $request)
    {

        $limit = $request->limit ?? 10;
        $offset = $request->offset ?? 0;

        $query = StocksSalesOrder::with([
            'client',
            'upload' => function($q){
                $q->where('type','order');
            }
        ]);

        // search
        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('sales_order_no','like','%'.$search.'%')
                ->orWhereHas('client', function($c) use ($search){
                    $c->where('name','like','%'.$search.'%')
                        ->orWhere('mobile','like','%'.$search.'%');
                });

            });

        }

        // total count
        $total = $query->count();

        // fetch orders
        $orders = $query
            ->orderBy('id','desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Sales orders fetched successfully',
            'total' => $total,
            'limit' => (int)$limit,
            'offset' => (int)$offset,
            'data' => $orders->map(function($order){
                return [
                    'id' => $order->id,
                    'sales_order_no' => $order->sales_order_no,
                    'date' => $order->so_date 
                        ? \Carbon\Carbon::parse($order->so_date)->format('d-m-Y')
                        : null,
                    'client' => [
                        'id' => $order->client->id ?? null,
                        'name' => $order->client->name ?? null,
                        'owner_name' => $order->client->owner_name ?? null,
                        'mobile' => $order->client->mobile ?? null,
                        'address' => $order->client->address ?? null,
                        'email' => $order->client->email ?? null
                    ],
                    'grand_total' => $order->grand_total,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'remain_due' => $order->remain_due,
                    'total_tax' => $order->total_tax,
                    'pdf' => $order->upload ? $order->upload->file_url : null
                ];
            })
        ]);
    }
    public function getSalesOrderDetail(Request $request)
    {

        $request->validate([
            'id' => 'required|exists:stocks_sales_orders,id'
        ]);

        $order = StocksSalesOrder::with([
            'client',
            'items.product',
            'upload'
        ])->find($request->id);

        $items = $order->items->map(function ($item) {

            return [
                'id' => $item->id,
                'uid' => $item->uid,
                'qty' => $item->qty,
                'price' => $item->price,
                'tax' => $item->tax,
                'sub_total' => $item->sub_total,
                'sub_total_tax' => $item->sub_total_tax,

                'product' => [
                    // 'id' => $item->product->id ?? null,
                    'uid' => $item->product->uid ?? null,
                    'name' => $item->product->name ?? null,
                    'size' => $item->product->size ?? null,
                    'color' => $item->product->color ?? null,
                    'list_price' => $item->product->list_price ?? null,
                    'sale_price' => $item->product->sale_price ?? null,
                    'stock' => $item->product->stock ?? null,
                    // 'status' => $item->product->status ?? null
                ]

            ];

        });

        return response()->json([
            'status' => true,
            'message' => 'Sales order detail fetched successfully',

            'data' => [

                'id' => $order->id,
                'sales_order_no' => $order->sales_order_no,

                'date' => $order->so_date 
                    ? \Carbon\Carbon::parse($order->so_date)->format('d-m-Y')
                    : null,

                'client' => [
                    'id' => $order->client->id ?? null,
                    'name' => $order->client->name ?? null,
                    'owner_name' => $order->client->owner_name ?? null,
                    'mobile' => $order->client->mobile ?? null,
                    'address' => $order->client->address ?? null,
                    'email' => $order->client->email ?? null,
                    // 'status' => $order->client->status ?? null
                ],

                'grand_total' => $order->grand_total,
                'total_tax' => $order->total_tax,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'remain_due' => $order->remain_due,
                'items' => $items,

                'pdf' => $order->upload ? [
                    'file_name' => $order->upload->file_name,
                    'url' => $order->upload->file_url
                ] : null

            ]

        ]);

    }
    public function deleteSalesOrder(Request $request)
    {

        $request->validate([
            'id' => 'required|exists:stocks_sales_orders,id'
        ]);

        DB::beginTransaction();

        try {

            $order = StocksSalesOrder::find($request->id);

            $items = StocksSalesOrderItem::where('sales_order_id',$order->id)->get();

            // restore stock
            foreach($items as $item){

                StocksProduct::where('uid',$item->uid)
                    ->increment('stock',$item->qty);

            }

            // delete items
            StocksSalesOrderItem::where('sales_order_id',$order->id)->delete();

            // delete order
            $order->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Sales order deleted successfully'
            ]);

        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }

    }

    public function generateSalesOrderPdf(Request $request)
    {

        $request->validate([
            'id' => 'required|exists:stocks_sales_orders,id'
        ]);

        $order = StocksSalesOrder::with([
            'client',
            'items.product'
        ])->find($request->id);

        if(!$order){
            return response()->json([
                'status'=>false,
                'message'=>'Sales order not found'
            ]);
        }

        // prevent duplicate pdf
        $existing = StocksUpload::where('type','order')
            ->where('number',$order->sales_order_no)
            ->first();

        if($existing){

            // delete previous file
            $oldPath = 'sales_orders/'.$existing->file_name;

            if(\Storage::disk('public')->exists($oldPath)){
                \Storage::disk('public')->delete($oldPath);
            }

            // delete DB record
            $existing->delete();
        }

        // generate html
        // $html = $this->salesOrderPdfBody($order);
        $html = '
            <style>
                body {
                    font-family: DejaVu Sans, sans-serif;
                    font-size: 9px;
                    color: #333;
                    margin: 10px;
                }

                /* WATERMARK */
                .invoice {
                    position: relative;
                }

                .bg-image {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 200px;
                    opacity: 0.05;
                    z-index: -1;
                }

                /* PAGE LAYOUT */
                .page-table {
                    width: 100%;
                    border-collapse: collapse;
                    border: none;
                    page-break-inside: avoid;
                }

                .page-table td {
                    width: 50%;
                    vertical-align: top;
                    padding: 5px;
                    border: none;
                }

                /* TABLE STYLE */
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 8px;
                    page-break-inside: auto;
                }

                th,
                td {
                    border: 1px solid #666;
                    padding: 3px 4px;
                    font-size: 9px;
                }

                th {
                    background: #f5f5f5;
                    text-align: center;
                }

                /* ALIGNMENTS */
                .center {
                    text-align: center;
                }

                .right {
                    text-align: right;
                }

                /* HEADER */
                .header-left {
                    float: left;
                }

                .header-right {
                    float: right;
                    text-align: right;
                }

                .clear {
                    clear: both;
                }

                /* TITLE */
                .title {
                    font-size: 16px;
                    font-weight: bold;
                    color: #c79b37;
                    margin-bottom: 5px;
                }

                /* LOGO */
                .logo {
                    height: 45px;
                }

                /* INFO TEXT */
                .info {
                    margin-top: 5px;
                    line-height: 1.4;
                    font-size: 9px;
                }

                /* FOOTER */
                .footer {
                    margin-top: 20px;
                    font-size: 8px;
                }

                /* TERMS */
                .terms {
                    float: left;
                    width: 70%;
                }

                /* SIGNATURE */
                .signature {
                    float: right;
                    width: 25%;
                    text-align: right;
                }

                .signature-line {
                    margin-top: 25px;
                    border-top: 1px solid #333;
                    width: 120px;
                    float: right;
                }

                /* CUT LINE BETWEEN INVOICES */
                .page-table td:first-child {
                    border-right: 1px dashed #999;
                }

                tr {
                    page-break-inside: avoid;
                }
            </style>

            <table class="page-table">
                <tr>
                    <td>
                        '.$this->salesOrderPdfBody($order).'
                    </td>
                    <td>
                        '.$this->salesOrderPdfBody($order).'
                    </td>
                </tr>
            </table>
        ';

        $pdf = Pdf::loadHTML($html)
        ->setPaper('a4','landscape')
        ->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true
        ]);

        $fileName = 'sales_order_'.$order->sales_order_no.'.pdf';
        $filePath = 'sales_orders/'.$fileName;

        \Storage::disk('public')->put($filePath,$pdf->output());

        $url = asset('storage/'.$filePath);

        StocksUpload::create([
            'type'=>'order',
            'number'=>$order->sales_order_no,
            'file_name'=>$fileName,
            'file_url'=>$url
        ]);

        return response()->json([
            'status'=>true,
            'message'=>'Sales order pdf generated successfully',
            'file_url'=>$url
        ]);
    }
    private function salesOrderPdfBody($order)
    {

        $logoPath = public_path('logos/liwaas_logo_Black.jpg');
        $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/'.$logoType.';base64,'.base64_encode($logoData);

        $bgPath = public_path('logos/flower-removebg-preview.png');
        $bgType = pathinfo($bgPath, PATHINFO_EXTENSION);
        $bgData = file_get_contents($bgPath);
        $bgBase64 = 'data:image/'.$bgType.';base64,'.base64_encode($bgData);


        $html = '
        <div class="invoice">
            <img src="'.$bgBase64.'" class="bg-image">

            <div class="header">

                <div class="header-left">

                    <div class="title">SALES ORDER</div>

                    <div class="info">
                        <strong>Order No :</strong> '.$order->sales_order_no.'<br>
                        <strong>Date :</strong> '.$order->created_at->format('d M Y').'<br>
                    </div>

                </div>

                <div class="header-right">

                    <img src="'.$logoBase64.'" class="logo"><br>

                    Memari, Burdwan<br>
                    West Bengal<br>
                    India, 713146

                </div>

                <div class="clear"></div>

            </div>

            <div class="bill">

                <strong>Bill To:</strong><br>

                Retail Name : '.($order->client->name ?? '-').'<br>
                Owner : '.($order->client->owner_name ?? '-').'<br>
                Mobile : '.($order->client->mobile ?? '-').'<br>
                Address : '.($order->client->address ?? '-').'

            </div>

            <table>
                <thead>
                    <tr>
                        <th width="8%">SN</th>
                        <th width="42%">ITEM DETAILS</th>
                        <th width="10%">QTY</th>
                        <th width="20%">PRICE</th>
                        <th width="20%">SUBTOTAL</th>
                    </tr>
                </thead>

                <tbody>
        ';

        $i = 1;

            foreach($order->items as $item){

                $product = $item->product;
                $html .= '
                    <tr style="border:1px solid #999;">
                        <td class="center">'.$i++.'</td>
                        <td>'.
                            ($product->name ?? '-') .
                            ' ('.
                            ($product->size ?? '-') .
                            ' / '.
                            ($product->color ?? '-') .
                            ')
                        </td>
                        <td class="center">'.$item->qty.'</td>
                        <td class="right">'.number_format($item->price,2).'</td>
                        <td class="right">'.number_format($item->sub_total,2).'</td>
                    </tr>
                    ';
            }
            $itemCount = count($order->items);

                if($itemCount < 5){
                    for($x = 0; $x < 4; $x++){
                        $html .= '
                        <tr style="border:1px dashed #999;">
                            <td>&nbsp;</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        ';
                    }
                }

        $html .= '

                    </tbody>

            </table>

            <table class="total-table" style="margin-top:10px; border-top:1px solid #666; border-bottom:1px solid #666;">

                <tr>
                    <td width="70%" class="right">TAX</td>
                    <td width="30%" class="right">'.number_format($order->total_tax,2).'</td>
                </tr>

                <tr>
                    <td class="right">GRAND TOTAL</td>
                    <td class="right">'.number_format($order->grand_total,2).'</td>
                </tr>

                <tr>
                    <td class="right">ADVANCE</td>
                    <td class="right">00</td>
                </tr>

                <tr>
                    <td class="right">DUE</td>
                    <td class="right">00</td>
                </tr>

            </table>

            <div class="footer" style="margin-top:30px;">
                <div class="terms">
                    <strong>TERM & CONDITION</strong><br>
                    Advance payment is non-refundable after order confirmation.
                    The remaining payment must be cleared within 15 days after delivery.
                    <br>
                    <p style="color:#c79b37; font-weight:bold;">
                        Feel Free to Reach Us : business.liwaas@gmail.com / +91 8348381252
                    </p>
                </div>

                <div class="signature">
                    <div class="signature-line"></div>
                    Recipient Signature
                </div>


                <div style="clear:both"></div>
            </div>
        </div>
        ';

        return $html;

    }

    // Client functions
    // CREATE CLIENT
    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $client = StocksClient::create($data);

        return response()->json([
            "success" => true,
            "message" => "Client created successfully",
            "data" => $client
        ]);
    }

    // GET ALL CLIENTS
    public function fetch(Request $request)
    {
        $query = StocksClient::query();

        if ($request->filled('search')) {
            $query->where('name','like','%'.$request->search.'%')
                  ->orWhere('mobile','like','%'.$request->search.'%');
        }

        $clients = $query->orderBy('id','desc')->get();

        return response()->json([
            "success" => true,
            "total" => $clients->count(),
            "data" => $clients
        ]);
    }

    // UPDATE CLIENT
    public function update(Request $request,$id)
    {
        $client = StocksClient::find($id);

        if(!$client){
            return response()->json([
                "success"=>false,
                "message"=>"Client not found"
            ],404);
        }

        $client->update($request->only([
            'name','owner_name','mobile','address','email','status'
        ]));

        return response()->json([
            "success"=>true,
            "message"=>"Client updated successfully",
            "data"=>$client
        ]);
    }

    // DELETE CLIENT
    public function delete($id)
    {
        $client = StocksClient::find($id);

        if(!$client){
            return response()->json([
                "success"=>false,
                "message"=>"Client not found"
            ],404);
        }

        $client->delete();

        return response()->json([
            "success"=>true,
            "message"=>"Client deleted successfully"
        ]);
    }

}
