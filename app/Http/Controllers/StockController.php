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
            'items.*.tax' => 'nullable|numeric'
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
                'total_tax' => 0
            ]);

            foreach ($request->items as $item) {

                $sub_total = $item['qty'] * $item['price'];
                $sub_total_tax = $item['tax'] ?? 0;

                $grand_total += $sub_total;
                $total_tax += $sub_total_tax;

                StocksSalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'uid' => $item['uid'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'tax' => $item['tax'] ?? 0,
                    'sub_total' => $sub_total,
                    'sub_total_tax' => $sub_total_tax
                ]);

                // reduce stock
                StocksProduct::where('uid', $item['uid'])
                    ->decrement('stock', $item['qty']);
            }

            $order->update([
                'grand_total' => $grand_total,
                'total_tax' => $total_tax
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
                    'grand_total' => $grand_total,
                    'total_tax' => $total_tax
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

    public function getSalesOrders(Request $request)
    {

        $limit = $request->limit ?? 10;
        $offset = $request->offset ?? 0;

        $query = StocksSalesOrder::with('client');

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
            'data' => $orders
        ]);
    }
    public function getSalesOrderDetail(Request $request)
    {

        $request->validate([
            'id' => 'required|exists:stocks_sales_orders,id'
        ]);

        $order = StocksSalesOrder::with([
            'client',
            'items.product'
        ])->find($request->id);

        return response()->json([
            'status' => true,
            'message' => 'Sales order detail fetched successfully',
            'data' => $order
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
            return response()->json([
                'status'=>true,
                'message'=>'Sales order pdf already exists',
                'file_url'=>$existing->file_url
            ]);
        }

        // generate html
        $html = $this->salesOrderPdfBody($order);

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

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

    $html = '

    <style>

    body{
        font-family: DejaVu Sans, sans-serif;
        font-size:12px;
        color:#333;
    }

    .header{
        width:100%;
        margin-bottom:20px;
    }

    .header-left{
        float:left;
    }

    .header-right{
        float:right;
        text-align:right;
    }

    .clear{
        clear:both;
    }

    h1{
        font-size:26px;
        color:#b8953b;
        margin:0;
    }

    .logo{
        font-size:28px;
        font-weight:bold;
    }

    .info{
        margin-top:15px;
    }

    table{
        width:100%;
        border-collapse:collapse;
        margin-top:20px;
    }

    th{
        background:#f3f3f3;
        font-weight:bold;
    }

    th,td{
        border:1px solid #999;
        padding:8px;
        text-align:left;
    }

    .total-table{
        margin-top:20px;
    }

    .footer{
        margin-top:40px;
        font-size:10px;
    }

    .signature{
        margin-top:40px;
        text-align:right;
    }

    </style>


    <div class="header">

    <div class="header-left">
    <h1>SALES ORDER</h1>

    <div class="info">
    <strong>Invoice :</strong> '.$order->sales_order_no.'<br>
    <strong>Date :</strong> '.$order->created_at->format('d M Y').'<br>
    </div>
    </div>

    <div class="header-right">
    <div class="logo">Liwaas</div>
    Memari, Burdwan<br>
    West Bengal<br>
    India - 713146
    </div>

    <div class="clear"></div>

    </div>



    <strong>Bill To :</strong><br>
    '.$order->client->name.'<br>
    '.$order->client->address.'<br>
    '.$order->client->mobile.'


    <table>

    <thead>
    <tr>
    <th width="5%">SN</th>
    <th width="45%">ITEM DETAILS</th>
    <th width="10%">QTY</th>
    <th width="20%">PRICE</th>
    <th width="20%">SUBTOTAL</th>
    </tr>
    </thead>

    <tbody>
    ';

    $i=1;

    foreach($order->items as $item){

    $html .= '

    <tr>
    <td>'.$i++.'</td>
    <td>'.($item->product->name ?? '-').' ('.$item->product->size.' / '.$item->product->color.')</td>
    <td>'.$item->qty.'</td>
    <td>'.number_format($item->price,2).'</td>
    <td>'.number_format($item->sub_total,2).'</td>
    </tr>

    ';

    }

    $html .= '

    </tbody>

    </table>


    <table class="total-table">

    <tr>
    <td width="70%" align="right"><strong>TAX</strong></td>
    <td width="30%">'.number_format($order->total_tax,2).'</td>
    </tr>

    <tr>
    <td align="right"><strong>GRAND TOTAL</strong></td>
    <td>'.number_format($order->grand_total,2).'</td>
    </tr>

    </table>


    <div class="footer">

    <strong>Term & Condition</strong><br>
    Advance payment is non-refundable after order confirmation. Balance payment must be cleared at the time of delivery.

    </div>


    <div class="signature">
    _____________________<br>
    SIGNATURE
    </div>

    ';

    return $html;

}

}
