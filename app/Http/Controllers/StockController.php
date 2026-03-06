<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StocksProduct;
use Illuminate\Support\Str;

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

        $uid = $last ? $last->uid + 1 : time();

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

    // public function getProductStocks(Request $request)
    // {

    //     $query = StocksProduct::query();

    //     // search
    //     if($request->search){
    //         $query->where(function($q) use ($request){
    //             $q->where('name','like','%'.$request->search.'%')
    //             ->orWhere('uid','like','%'.$request->search.'%');
    //         });
    //     }

    //     // size filter
    //     if($request->size){
    //         $query->where('size',$request->size);
    //     }

    //     // color filter
    //     if($request->color){
    //         $query->where('color',$request->color);
    //     }

    //     // status filter
    //     if($request->status !== null){
    //         $query->where('status',$request->status);
    //     }

    //     $products = $query
    //         ->orderBy('id','desc')
    //         ->paginate($request->limit ?? 10);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Stocks fetched successfully',
    //         'data' => $products
    //     ]);

    // }

    public function getProductStocks(Request $request)
    {

        $query = StocksProduct::query();

        // search by name or uid
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('uid', 'like', '%' . $request->search . '%');
            });
        }

        // filter by size
        if ($request->filled('size')) {
            $query->where('size', $request->size);
        }

        // filter by color
        if ($request->filled('color')) {
            $query->where('color', $request->color);
        }

        // filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $limit = $request->limit ?? 10;

        $products = $query
            ->orderBy('id', 'desc')
            ->paginate($limit);

        return response()->json([
            'status' => true,
            'message' => 'Stocks fetched successfully',
            'data' => $products
        ]);
    }
}
