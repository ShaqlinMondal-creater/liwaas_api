<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\ProductVariations;
use App\Models\ProductReview;
use App\Models\Upload;
use App\Models\Category;
use Carbon\Carbon;


class SectionViewController extends Controller
{
    //////////////////////////      FETCH PRODUCTS ONLY     \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    // New Arriaval Products
    public function getNewArriaval()
    {
        $cutoffDate = Carbon::now()->subHours(72);

        $products = Product::with(['brand', 'category', 'upload', 'variations'])
            ->where('product_status', 'active')
            ->where('created_at', '>=', $cutoffDate)
            ->orderBy('created_at', 'desc')
            ->get();

        $filtered = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'aid' => $product->aid,
                'name' => $product->name,
                'gender' => $product->gender,
                'image_url' => $product->image_url,
                'upload_id' => $product->upload_id,
                'product_status' => $product->product_status,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                ] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'upload' => $product->upload ? [
                    'id' => $product->upload->id,
                    'url' => $product->upload->url,
                ] : null,
                'variations' => $product->variations->map(function ($var) {
                    return [
                        'id' => $var->id,
                        'uid' => $var->uid,
                        'aid' => $var->aid,
                        'color' => $var->color,
                        'size' => $var->size,
                        'regular_price' => $var->regular_price,
                        'sell_price' => $var->sell_price,
                        'images_id' => $var->images_id,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'New arrivals fetched successfully.',
            'data' => $filtered,
        ]);
    }

    // Trending Products
    public function getTrendings(Request $request)
    {
        $minQty = $request->query('min_qty', 10); // default to 10

        // Step 1: Get UID with quantity sum > threshold
        $trendingUIDs = OrderItems::select('uid')
            ->selectRaw('SUM(quantity) as total_qty')
            ->groupBy('uid')
            ->havingRaw('SUM(quantity) >= ?', [$minQty])
            ->pluck('uid');

        if ($trendingUIDs->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No trending products found.',
                'data' => []
            ]);
        }

        // Step 2: Get variations with these UIDs
        $variations = ProductVariations::whereIn('uid', $trendingUIDs)->get();

        // Step 3: Get unique AIDs from variations
        $aids = $variations->pluck('aid')->unique();

        // Step 4: Fetch products with relationships
        $products = Product::with(['brand', 'category', 'upload', 'variations'])
            ->whereIn('aid', $aids)
            ->where('product_status', 'active')
            ->get();

        // Step 5: Format the response
        $filtered = $products->map(function ($product) use ($trendingUIDs) {
            return [
                'id' => $product->id,
                'aid' => $product->aid,
                'name' => $product->name,
                'gender' => $product->gender,
                'image_url' => $product->image_url,
                'upload_id' => $product->upload_id,
                'product_status' => $product->product_status,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                ] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
                'upload' => $product->upload ? [
                    'id' => $product->upload->id,
                    'url' => $product->upload->url,
                ] : null,
                'variations' => $product->variations
                    ->whereIn('uid', $trendingUIDs)
                    ->map(function ($var) {
                        return [
                            'id' => $var->id,
                            'uid' => $var->uid,
                            'aid' => $var->aid,
                            'color' => $var->color,
                            'size' => $var->size,
                            'regular_price' => $var->regular_price,
                            'sell_price' => $var->sell_price,
                            'images_id' => $var->images_id,
                        ];
                    })->values()
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Trending products fetched successfully.',
            'data' => $filtered,
        ]);
    }

    // Gallery Products
    public function getGallery()
    {
        $reviewedUids = ProductReview::pluck('uid')->unique();

        $variations = ProductVariations::with('product.brand', 'product.category', 'product.upload')
            ->whereIn('uid', $reviewedUids)
            ->get();

        $response = $variations->map(function ($variation) {
            $product = $variation->product;

            $productReviews = ProductReview::with('user')
                ->where('uid', $variation->uid)
                ->get();

            return [
                'uid' => $variation->uid,
                'aid' => $product->aid,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'gender' => $product->gender,
                'product_status' => $product->product_status,
                // 'upload_id' => $product->upload_id,
                // 'upload' => $product->upload ? [
                //     'id' => $product->upload->id,
                //     'url' => $product->upload->url,
                // ] : null,
                // 'brand' => $product->brand ? [
                //     'id' => $product->brand->id,
                //     'name' => $product->brand->name,
                // ] : null,
                // 'category' => $product->category ? [
                //     'id' => $product->category->id,
                //     'name' => $product->category->name,
                // ] : null,
                'variation' => [
                    'id' => $variation->id,
                    'color' => $variation->color,
                    'size' => $variation->size,
                ],
                'reviews' => $productReviews->map(function ($review) {
                    $uploadImageIds = is_array($review->upload_images) ? $review->upload_images : (array) $review->upload_images;

                    $reviewImages = [];

                    if (!empty($uploadImageIds)) {
                        $uploads = Upload::whereIn('id', $uploadImageIds)->get();
                        $reviewImages = $uploads->map(function ($upload) {
                            return [
                                'upload_id' => $upload->id,
                                'upload_url' => $upload->url,
                            ];
                        })->toArray();
                    }

                    return [
                        'id' => $review->id,
                        // 'user' => [
                        //     'id' => $review->user->id,
                        //     'name' => $review->user->name,
                        // ],
                        'total_star' => $review->total_star,
                        // 'comments' => $review->comments,
                        // 'upload' => $uploadImageIds,
                        'review_images' => $reviewImages,
                    ];
                })
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Gallery products with reviews fetched successfully.',
            'data' => $response
        ]);
    }

    // Category Wise
    public function getCategoryProducts($category_id)
    {
        // Step 1: Fetch the category
        $category = Category::find($category_id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        // Step 2: Fetch products in that category with brand, upload, and variations
        $products = Product::with(['brand', 'category', 'upload', 'variations'])
            ->where('category_id', $category_id)
            ->get();

        // Step 3: Format response with variations included
        $response = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'aid' => $product->aid,
                'name' => $product->name,
                'brand_id' => $product->brand_id,
                'category_id' => $product->category_id,
                // 'slug' => $product->slug,
                // 'description' => $product->description,
                // 'specification' => $product->specification,
                // 'gender' => $product->gender,
                // 'cod' => $product->cod,
                // 'shipping' => $product->shipping,
                // 'ratings' => $product->ratings,
                // 'keyword' => $product->keyword,
                // 'image_url' => $product->image_url,
                // 'upload_id' => $product->upload_id,
                'product_status' => $product->product_status,
                // 'added_by' => $product->added_by,
                // 'custom_design' => $product->custom_design,
                // 'created_at' => $product->created_at,
                // 'updated_at' => $product->updated_at,

                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                    // 'logo' => $product->brand->logo,
                    // 'created_at' => $product->brand->created_at,
                    // 'updated_at' => $product->brand->updated_at,
                ] : null,

                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    // 'logo' => $product->category->logo,
                    // 'created_at' => $product->category->created_at,
                    // 'updated_at' => $product->category->updated_at,
                ] : null,

                'upload' => $product->upload ? [
                    'id' => $product->upload->id,
                    'url' => $product->upload->url,
                ] : null,

                'variations' => $product->variations->map(function ($variation) {
                    // Convert comma-separated string into array of IDs
                    $imageIds = explode(',', $variation->images_id);
                    $imageIds = array_filter($imageIds); // remove empty values

                    // Fetch uploads by IDs
                    $uploads = \App\Models\Upload::whereIn('id', $imageIds)->get();

                    // Map to upload_id and upload_url
                    $images = $uploads->map(function ($upload) {
                        return [
                            'upload_id' => $upload->id,
                            'upload_url' => $upload->url,
                        ];
                    });

                    return [
                        'id' => $variation->id,
                        'uid' => $variation->uid,
                        'regular_price' => $variation->regular_price,
                        'sell_price' => $variation->sell_price,
                        // 'currency' => $variation->currency,
                        // 'gst' => $variation->gst,
                        // 'stock' => $variation->stock,
                        // 'images_id' => $variation->images_id,
                        'color' => $variation->color,
                        'size' => $variation->size,
                        'images' => $images, // ✅ Added image URLs based on images_id
                    ];
                }),

            ];
        });

        return response()->json([
            'success' => true,
            'message' => "Products for category '{$category->name}' fetched successfully.",
            'data' => $response
        ]);
    }

    //////////////////////////      FETCH PRODUCTS ONLY     \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\


    //////////////////////////      IMPORT ABOVE IN PRODUCTS SECTION TABLE     \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\





    //////////////////////////      IMPORT ABOVE IN PRODUCTS SECTION TABLE     \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

}
