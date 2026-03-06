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

        $product = StocksProduct::create([
            'uid' => Str::uuid(),
            'name' => $request->name,
            'size' => $request->size,
            'color' => $request->color,
            'list_price' => $request->list_price,
            'sale_price' => $request->sale_price,
            'stock' => $request->stock,
            'status' => 1
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Stock product added successfully',
            'data' => $product
        ]);

    }
}
