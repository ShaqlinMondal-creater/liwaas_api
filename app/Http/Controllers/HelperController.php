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
    public function getFilters()
    {
        // Get all categories
        $categories = Category::select('id', 'name')->get();

        // Unique sizes
        $sizes = ProductVariations::whereNotNull('size')->distinct()->pluck('size');

        // Unique colors
        $colors = ProductVariations::whereNotNull('color')->distinct()->pluck('color');

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

    // public function getFilters()
    // {
    //     // Get all categories
    //     $categories = Category::select('id', 'name')->get();

    //     // Unique sizes
    //     $sizes = ProductVariations::whereNotNull('size')
    //         ->distinct()
    //         ->pluck('size');

    //     // Unique colors from DB
    //     $dbColors = ProductVariations::whereNotNull('color')
    //         ->distinct()
    //         ->pluck('color')
    //         ->toArray();

    //     // Load colors.json
    //     $path = storage_path('app/data/colors.json');

    //     $colorData = [];
    //     if (file_exists($path)) {
    //         $json = file_get_contents($path);
    //         $decoded = json_decode($json, true);

    //         if (isset($decoded['colors'])) {
    //             $colorData = $decoded['colors'];
    //         }
    //     }

    //     // Match DB colors with JSON colors
    //     $colors = collect($colorData)
    //         ->whereIn('name', $dbColors)
    //         ->values();

    //     // Min / Max price
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

    // For get colors from colors.json file
    public function getAllColors()
    {
        $path = storage_path('app/data/colors.json');

        if (!file_exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Color file not found.'
            ], 404);
        }

        $colors = json_decode(file_get_contents($path), true);

        return response()->json([
            'success' => true,
            'data' => $colors['colors'] ?? []
        ]);
    }

    public function addColor(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string'
        ]);

        $path = storage_path('app/data/colors.json');

        $data = json_decode(file_get_contents($path), true);

        $colors = $data['colors'] ?? [];

        // Check duplicate
        foreach ($colors as $color) {
            if (strtolower($color['name']) === strtolower($request->name)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Color already exists.'
                ], 409);
            }
        }

        $colors[] = [
            'code' => $request->code,
            'name' => $request->name
        ];

        file_put_contents($path, json_encode(['colors' => $colors], JSON_PRETTY_PRINT));

        return response()->json([
            'success' => true,
            'message' => 'Color added successfully.',
            'data' => [
                'name' => $request->name,
                'code' => $request->code
            ]
        ]);
    }

    public function deleteColor(Request $request)
    {
        $request->validate([
            'name' => 'required|string'
        ]);

        $path = storage_path('app/data/colors.json');

        $data = json_decode(file_get_contents($path), true);

        $colors = collect($data['colors']);

        $filtered = $colors->reject(function ($color) use ($request) {
            return strtolower($color['name']) === strtolower($request->name);
        })->values();

        file_put_contents(
            $path,
            json_encode(['colors' => $filtered], JSON_PRETTY_PRINT)
        );

        return response()->json([
            'success' => true,
            'message' => 'Color deleted successfully.'
        ]);
    }

    public function updateColor(Request $request)
    {
        $request->validate([
            'old_name' => 'required|string',
            'name' => 'required|string',
            'code' => 'required|string'
        ]);

        $path = storage_path('app/data/colors.json');

        if (!file_exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Color file not found.'
            ], 404);
        }

        $data = json_decode(file_get_contents($path), true);
        $colors = $data['colors'] ?? [];

        $updated = false;

        foreach ($colors as &$color) {

            if (strtolower($color['name']) === strtolower($request->old_name)) {

                $color['name'] = $request->name;
                $color['code'] = $request->code;

                $updated = true;
                break;
            }
        }

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Color not found.'
            ], 404);
        }

        file_put_contents($path, json_encode(['colors' => $colors], JSON_PRETTY_PRINT));

        return response()->json([
            'success' => true,
            'message' => 'Color updated successfully.',
            'data' => [
                'name' => $request->name,
                'code' => $request->code
            ]
        ]);
    }

}
