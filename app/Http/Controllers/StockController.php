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

        .page-table{
        width:100%;
        border-collapse:collapse;
        }

        .page-table td{
        width:50%;
        vertical-align:top;
        padding:10px;
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

        $pdf = Pdf::loadHTML($html)->setPaper('a4','landscape');

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

        <style>

            body{
            font-family: DejaVu Sans, sans-serif;
            font-size:11px;
            color:#333;
            position:relative;
            }

            .bg-image{
            position:absolute;
            top:35%;
            left:20%;
            width:400px;
            opacity:0.08;
            z-index:-1;
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

            .logo{
            height:70px;
            }

            .clear{
            clear:both;
            }

            .title{
            font-size:24px;
            font-weight:bold;
            color:#c79b37;
            margin-bottom:10px;
            }

            .info{
            margin-top:10px;
            line-height:1.6;
            }

            .bill{
            margin-top:20px;
            }

            table{
            width:100%;
            border-collapse:collapse;
            margin-top:15px;
            }

            th,td{
            border:1px solid #666;
            padding:6px;
            font-size:11px;
            }

            th{
            background:#f5f5f5;
            text-align:center;
            }

            .center{
            text-align:center;
            }

            .right{
            text-align:right;
            }

            .total-table td{
            font-weight:bold;
            }

            .footer{
            margin-top:50px;
            font-size:10px;
            }

            .terms{
            float:left;
            width:70%;
            }

            .signature{
            float:right;
            width:25%;
            text-align:right;
            }

            .signature-line{
            margin-top:40px;
            border-top:1px solid #333;
            width:150px;
            float:right;
            }

        </style>


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

        $i=1;
        $rows = 8; // fixed rows like template
        $count = count($order->items);

        foreach($order->items as $item){

            $product = $item->product;

            $html .= '
            <tr>
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

        // empty rows to match layout
        for($x=$count;$x<$rows;$x++){

            $html .= '

            <tr>
            <td>&nbsp;</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            </tr>

            ';

        }

        $html .= '

        </tbody>

        </table>



        <table class="total-table">

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
        <td class="right">0.00</td>
        </tr>

        <tr>
        <td class="right">DUE</td>
        <td class="right">'.number_format($order->grand_total,2).'</td>
        </tr>

        </table>



        <div class="footer">

        <div class="terms">

        <strong>TERM & CONDITION</strong><br>

        Advance payment is non-refundable after order confirmation.
        Balance payment must be cleared at the time of delivery.

        </div>


        <div class="signature">

        <div class="signature-line"></div>
        SIGNATURE

        </div>


        <div style="clear:both"></div>

        </div>

        ';

        return $html;

    }
}
