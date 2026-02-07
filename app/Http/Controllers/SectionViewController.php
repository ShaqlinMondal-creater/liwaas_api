<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\ProductVariations;
use App\Models\ProductReview;
use App\Models\Upload;
use App\Models\Category;
use App\Models\SectionView;
use Carbon\Carbon;


class SectionViewController extends Controller
{
    
    public function markedSectionProducts(Request $request) // Marked Section Products
    {
        $request->validate([
            'uid' => 'required|string',
            'section_name' => 'required|string',
        ]);

        try {
            // Check if same uid and section_name already exists
            $existing = SectionView::where('uid', $request->uid)
                ->where('section_name', $request->section_name)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This product is already marked in this section.',
                    'data' => $existing,
                ], 200);
            }

            // Create new record if not exists
            $sectionView = SectionView::create([
                'uid' => $request->uid,
                'section_name' => $request->section_name,
                'status' => 1,
                'force_status' => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Section product marked successfully.',
                'data' => $sectionView,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }   
    public function getSectionsProducts(Request $request) // Get Section products
    {
        $sectionName = $request->input('section_name');
        $status = $request->input('status'); // optional
        $limit = (int) $request->input('limit', 12);
        $offset = (int) $request->input('offset', 0);

        // Step 1: Get section views
        $query = SectionView::query();

        if (!empty($sectionName)) {
            $query->where('section_name', $sectionName);
        }

        if (!is_null($status)) {
            $query->where('status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        $total = $query->count();

        $sectionViews = $query
            ->orderBy('section_name', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $uids = $sectionViews->pluck('uid')->toArray();

        // Step 2: Fetch all variations with product info
        $variations = \App\Models\ProductVariations::with([
            'product',
            'product.brand',
            'product.category',
            'product.upload'
        ])->whereIn('uid', $uids)->get()
        ->keyBy('uid'); // So we can fetch by UID quickly

        $response = [];

        foreach ($sectionViews as $section) {
            $variation = $variations->get($section->uid);

            if (!$variation || !$variation->product) continue;

            $product = $variation->product;

            // Resolve image uploads
            $imageIds = array_filter(explode(',', $variation->images_id));
            $uploads = \App\Models\Upload::whereIn('id', $imageIds)->get();

            $images = $uploads->map(function ($upload) {
                return [
                    'upload_id' => $upload->id,
                    'upload_url' => url($upload->url),
                ];
            });

            $response[] = [
                'section' => [
                    'id' => $section->id,
                    'section_name' => $section->section_name,
                    'uid' => $section->uid,
                    'status' => $section->status,
                    'force_status' => $section->force_status
                ],
                'product' => [
                    'id' => $product->id,
                    'aid' => $product->aid,
                    'name' => $product->name,
                    'slug' => $product->slug,
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
                    'variation' => [
                        'id' => $variation->id,
                        'uid' => $variation->uid,
                        'aid' => $variation->aid,
                        'color' => $variation->color,
                        'size' => $variation->size,
                        'regular_price' => $variation->regular_price,
                        'sell_price' => $variation->sell_price,
                        'images' => $images,
                    ]
                ]
            ];
        }

        return response()->json([
            'success' => true,
            'message' => $sectionName
                ? "Products for section '{$sectionName}' fetched successfully."
                : "Products for all sections fetched successfully.",
            'total' => $total,
            'data' => $response
        ]);
    }

    public function addSection(Request $request) // Add section 
    {
        $validated = $request->validate([
            'section_name' => 'required|string',
            'uid' => 'required|integer',
            'status' => 'nullable|boolean',
            'force_status' => 'nullable|boolean',
        ]);

        // ✅ Check if section_name + uid already exists
        $exists = SectionView::where('section_name', $validated['section_name'])
            ->where('uid', $validated['uid'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This section already exists for the user.'
            ], 409); // Conflict
        }

        $section = SectionView::create([
            'section_name' => $validated['section_name'],
            'uid' => $validated['uid'],
            'status' => $request->input('status', 0),
            'force_status' => $request->input('force_status', 0)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Section created successfully.',
            'data' => $section->makeHidden(['created_at', 'updated_at'])
        ]);
    }
    public function getSections(Request $request) // Get Sections Datas With filters
    {
        $limit = (int) $request->input('limit', 15);
        $offset = (int) $request->input('offset', 0);

        $query = SectionView::query();

        // ✅ Filter by section_name (exact or partial match)
        if ($request->has('section')) {
            $query->where('section_name', 'like', '%' . $request->input('section') . '%');
        }

        // ✅ Filter by uid
        if ($request->has('search')) {
            $query->where('uid', $request->input('search'));
        }

        // ✅ Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // ✅ Filter by force_status
        if ($request->has('force')) {
            $query->where('force_status', $request->input('force'));
        }

        $total = $query->count(); // total count before pagination

        $sections = $query->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Sections fetched successfully.',
            'data' => $sections->makeHidden(['created_at', 'updated_at']),
            'total' => $total
        ]);
    }
    public function deleteSections($id) // Delete Section through ID's
    {
        $section = SectionView::find($id);

        if (!$section) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found.'
            ], 404);
        }

        $section->delete();

        return response()->json([
            'success' => true,
            'message' => 'Section deleted successfully.'
        ]);
    }
    public function updateSection(Request $request, $id) // Update Sections
    {
        $section = SectionView::where('id', $id)->first();

        if (!$section) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found.'
            ], 404);
        }

        $validated = $request->validate([
            'section_name'  => 'sometimes|string',
            'status'        => 'sometimes|boolean',
            'force_status'  => 'sometimes|boolean',
        ]);

        $section->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Section updated successfully.',
            'data'    => $section->makeHidden(['created_at', 'updated_at']),
        ]);
    }

    //////////////////////////      FETCH PRODUCTS ONLY     \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public function getNewArriaval() // New Arriaval Products
    {
        $cutoffDate = Carbon::now()->subHours(72);

        $products = Product::with(['brand', 'category', 'upload', 'variations'])
            ->where('product_status', 'active')
            ->where('created_at', '>=', $cutoffDate)
            ->orderBy('created_at', 'desc')
            ->get();

        $flattened = [];
        $count = 0;

        foreach ($products as $product) {
            foreach ($product->variations as $variation) {
                if ($count >= 15) break 2; // stop both loops

                $flattened[] = [
                    'aid' => $product->aid,
                    'id' => $product->id,                    
                    'name' => $product->name,
                    'variation' => [
                        'id' => $variation->id,
                        'uid' => $variation->uid,
                        // 'aid' => $variation->aid,
                        'color' => $variation->color,
                        'size' => $variation->size,
                        // 'regular_price' => $variation->regular_price,
                        // 'sell_price' => $variation->sell_price,
                        // 'images_id' => $variation->images_id,
                    ]
                ];

                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'New arrivals fetched successfully.',
            'data' => $flattened,
        ]);
    }    

    public function getTrendings(Request $request)
    {
        // Step 1: Get all Trending section UIDs
        $sectionUIDs = Section::where('section_name', 'Trending')
            ->where('status', 1)
            ->pluck('uid');

        if ($sectionUIDs->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => "Products for section 'Trending' fetched successfully.",
                'total' => 0,
                'data' => []
            ]);
        }

        // Step 2: Get variations using those UIDs
        $variations = ProductVariations::whereIn('uid', $sectionUIDs)->get();

        if ($variations->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => "Products for section 'Trending' fetched successfully.",
                'total' => 0,
                'data' => []
            ]);
        }

        // Step 3: Group variations by AID
        $grouped = $variations->groupBy('aid');

        // Step 4: Fetch products in one query
        $products = Product::with(['brand', 'category', 'upload'])
            ->whereIn('aid', $grouped->keys())
            ->where('product_status', 'active')
            ->get()
            ->keyBy('aid');

        $finalProducts = [];

        foreach ($grouped as $aid => $vars) {

            if (!isset($products[$aid])) continue;

            $product = $products[$aid];

            $finalProducts[] = [
                'id' => $product->id,
                'aid' => $product->aid,
                'name' => $product->name,
                'slug' => $product->slug,
                'gender' => $product->gender,
                'image_url' => $product->image_url,
                'upload_id' => $product->upload_id,
                'product_status' => $product->product_status,
                'brand' => $product->brand,
                'category' => $product->category,
                'upload' => $product->upload,
                'variations' => $vars->map(function ($var) {
                    return [
                        'id' => $var->id,
                        'uid' => $var->uid,
                        'color' => $var->color,
                        'size' => $var->size,
                        'regular_price' => $var->regular_price,
                        'sell_price' => $var->sell_price,
                        'images' => $var->images ?? []
                    ];
                })->values()
            ];
        }

        return response()->json([
            'success' => true,
            'message' => "Products for section 'Trending' fetched successfully.",
            'total' => count($finalProducts),
            'data' => [
                'section' => 'Trending',
                'products' => $finalProducts
            ]
        ]);
    }

    // public function getTrendings(Request $request) // Trending Products
    // {
    //     $minQty = $request->query('min_qty', 15); // default to 10

    //     // Step 1: Get UID with quantity sum > threshold
    //     $trendingUIDs = OrderItems::select('uid')
    //         ->selectRaw('SUM(quantity) as total_qty')
    //         ->groupBy('uid')
    //         ->havingRaw('SUM(quantity) >= ?', [$minQty])
    //         ->pluck('uid');

    //     if ($trendingUIDs->isEmpty()) {
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'No trending products found.',
    //             'data' => []
    //         ]);
    //     }

    //     // Step 2: Get variations with these UIDs
    //     $variations = ProductVariations::whereIn('uid', $trendingUIDs)->get();

    //     // Step 3: Get unique AIDs from variations
    //     $aids = $variations->pluck('aid')->unique();

    //     // Step 4: Fetch products with relationships
    //     $products = Product::with(['brand', 'category', 'upload', 'variations'])
    //         ->whereIn('aid', $aids)
    //         ->where('product_status', 'active')
    //         ->get();

    //     // Step 5: Format the response
    //     $filtered = $products->map(function ($product) use ($trendingUIDs) {
    //         return [
    //             'id' => $product->id,
    //             'aid' => $product->aid,
    //             'name' => $product->name,
    //             // 'gender' => $product->gender,
    //             // 'image_url' => $product->image_url,
    //             // 'upload_id' => $product->upload_id,
    //             // 'product_status' => $product->product_status,
    //             // 'brand' => $product->brand ? [
    //             //     'id' => $product->brand->id,
    //             //     'name' => $product->brand->name,
    //             // ] : null,
    //             // 'category' => $product->category ? [
    //             //     'id' => $product->category->id,
    //             //     'name' => $product->category->name,
    //             // ] : null,
    //             // 'upload' => $product->upload ? [
    //             //     'id' => $product->upload->id,
    //             //     'url' => $product->upload->url,
    //             // ] : null,
    //             'variations' => $product->variations
    //                 ->whereIn('uid', $trendingUIDs)
    //                 ->map(function ($var) {
    //                     return [
    //                         'id' => $var->id,
    //                         'uid' => $var->uid,
    //                         // 'aid' => $var->aid,
    //                         'color' => $var->color,
    //                         'size' => $var->size,
    //                         // 'regular_price' => $var->regular_price,
    //                         // 'sell_price' => $var->sell_price,
    //                         // 'images_id' => $var->images_id,
    //                     ];
    //                 })->values()
    //         ];
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Trending products fetched successfully.',
    //         'data' => $filtered,
    //     ]);
    // } 

    public function getGallery() // Gallery Products
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
                // 'gender' => $product->gender,
                // 'product_status' => $product->product_status,
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
                                'upload_url' => url($upload->url),
                            ];
                        })->toArray();
                    }

                    return [
                        'id' => $review->id,
                        'user' => [
                            'id' => $review->user->id,
                            'name' => $review->user->name,
                        ],
                        'total_star' => $review->total_star,
                        'comments' => $review->comments,
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
    public function getCategoryProducts($category_id) // Category Wise
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
            ->take(5) // ✅ Limit to 5 products
            ->get();

        // Step 3: Format response with variations included
        $response = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'aid' => $product->aid,
                'name' => $product->name,
                // 'brand_id' => $product->brand_id,
                // 'category_id' => $product->category_id,
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

                // 'upload' => $product->upload ? [
                //     'id' => $product->upload->id,
                //     'url' => $product->upload->url,
                // ] : null,

                'variations' => $product->variations->take(1)->map(function ($variation) {
                    // Convert comma-separated string into array of IDs
                    $imageIds = explode(',', $variation->images_id);
                    $imageIds = array_filter($imageIds); // remove empty values

                    // Fetch uploads by IDs
                    $uploads = \App\Models\Upload::whereIn('id', $imageIds)->get();

                    // Map to upload_id and upload_url
                    $images = $uploads->map(function ($upload) {
                        return [
                            'upload_id' => $upload->id,
                            'upload_url' => url($upload->url),
                        ];
                    });

                    return [
                        'id' => $variation->id,
                        'uid' => $variation->uid,
                        // 'regular_price' => $variation->regular_price,
                        // 'sell_price' => $variation->sell_price,
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


}
