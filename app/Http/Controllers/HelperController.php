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
        $categories = Category::select('id','name')->get();

        $sizes = ProductVariations::whereNotNull('size')
            ->distinct()
            ->pluck('size');

        $colors = ProductVariations::whereNotNull('color')
            ->distinct()
            ->pluck('color');

        $price = ProductVariations::select(
            DB::raw('MIN(sell_price) as min_price'),
            DB::raw('MAX(sell_price) as max_price')
        )->first();

        // Load color json
        $colorJson = json_decode(
            Storage::get('data/colors.json'),
            true
        );

        $colorMap = collect($colorJson['colors'])->keyBy(function($item){
            return strtolower(trim($item['name']));
        });

        // Attach hex code
        $colorsWithCode = $colors->map(function($color) use ($colorMap){

    $key = strtolower(trim($color));

    $code = '#e5e7eb';

    if($colorMap->has($key)){
        $code = $colorMap[$key]['code'];
    }

    return [
        'name' => $color,
        'code' => $code
    ];
});


        return response()->json([
            'success' => true,
            'categories' => $categories,
            'sizes' => $sizes,
            'colors' => $colorsWithCode,
            'price' => [
                'min' => $price->min_price,
                'max' => $price->max_price
            ]
        ]);
    }

}
