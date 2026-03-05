<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\ProductVariations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HelperController extends Controller
{
    // Get all product filter objects
    // public function getFilters()
    // {
    //     // Get all categories
    //     $categories = Category::select('id', 'name')->get();

    //     // Unique sizes
    //     $sizes = ProductVariations::whereNotNull('size')->distinct()->pluck('size');

    //     // Unique colors
    //     $colors = ProductVariations::whereNotNull('color')->distinct()->pluck('color');

    //     // Min/Max price
    //     $price = ProductVariations::select(
    //         DB::raw('MIN(sell_price) as min_price'),
    //         DB::raw('MAX(sell_price) as max_price')
    //     )->first();

    //     return response()->json([
    //         'success' => true,
    //         'categories' => $categories,
    //         'sizes' => $sizes,
    //         'colors' => $colors,
    //         'price' => [
    //             'min' => $price->min_price,
    //             'max' => $price->max_price
    //         ]
    //     ]);
    // }

    public function getFilters()
{
    // Get all categories
    $categories = Category::select('id', 'name')->get();

    // Unique sizes
    $sizes = ProductVariations::whereNotNull('size')
        ->distinct()
        ->pluck('size');

    // Unique colors from DB
    $dbColors = ProductVariations::whereNotNull('color')
        ->distinct()
        ->pluck('color');

    // Load colors.json
    $colorJson = Storage::get('data/colors.json');
    $colorData = json_decode($colorJson, true);

    // Match DB colors with JSON colors
    $colors = collect($colorData['colors'])
        ->whereIn('name', $dbColors)
        ->values();

    // Min/Max price
    $price = ProductVariations::select(
        DB::raw('MIN(sell_price) as min_price'),
        DB::raw('MAX(sell_price) as max_price')
    )->first();

    return response()->json([
        'success' => true,
        'categories' => $categories,
        'sizes' => $sizes,
        'colors' => $colors,
        'price' => [
            'min' => $price->min_price,
            'max' => $price->max_price
        ]
    ]);
}


}
