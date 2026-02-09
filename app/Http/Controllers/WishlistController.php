<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Product;
use App\Models\ProductVariations;
use App\Models\Upload;

class WishlistController extends Controller
{
    public function addWishlist(Request $request)
    {
        // ✅ Step 1: Validate the incoming request
        $validated = $request->validate([
            'products_id' => 'required|exists:products,id',
            'aid'         => 'required|string',
            'uid'         => 'required|integer',
        ]);

        $userId = auth()->id();

        // ✅ Step 2: Confirm the variation exists for given aid + uid
        $variation = ProductVariations::where('uid', $validated['uid'])
                                    ->where('aid', $validated['aid'])
                                    ->first();

        if (!$variation) {
            return response()->json([
                'success' => false,
                'message' => 'Variation not found.',
            ], 404);
        }

        // ✅ Step 3: Check if the product variation is already in the user's wishlist
        $alreadyExists = Wishlist::where('user_id', $userId)
                                ->where('products_id', $validated['products_id'])
                                ->where('uid', $validated['uid'])
                                ->exists();

        if ($alreadyExists) {
            return response()->json([
                'success' => false,
                'message' => 'This product variation is already in your wishlist.',
            ], 409); // Conflict
        }

        // ✅ Step 4: Add to wishlist
        $wishlist = Wishlist::create([
            'user_id'     => $userId,
            'products_id' => $validated['products_id'],
            'aid'         => $validated['aid'],
            'uid'         => $validated['uid'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product variation added to wishlist.',
            'data'    => $wishlist->makeHidden(['created_at', 'updated_at']),
        ], 201);
    }

    public function getUserWishlist()
    {
        $userId = auth()->id();

        $wishlists = Wishlist::with(['product:id,aid,name,slug', 'variation'])
                            ->where('user_id', $userId)
                            ->get();

        if ($wishlists->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Your wishlist is empty.',
                'data'    => []
            ], 200);
        }

        // Add images to variation
        $data = $wishlists->map(function ($item) {
            $variation = $item->variation;

            // Process variation images
            $images = [];
            if ($variation && $variation->images_id) {
                $imageIds = array_filter(explode(',', $variation->images_id));
                $uploads = Upload::whereIn('id', $imageIds)->pluck('url')->toArray();

                $images = array_map(function ($path) {
                    return asset($path);
                }, $uploads);
            }
            return [
                'id'           => $item->id,
                'product_id'   => $item->products_id,
                'variation_uid'=> $item->uid,
                'variation_aid'=> $item->aid,
                'created_at'   => $item->created_at,
                'product'      => [
                    'id'   => $item->product->id ?? null,
                    'aid'  => $item->product->aid ?? null,
                    'name' => $item->product->name ?? null,
                    'slug' => $item->product->slug ?? null,
                ],
                'variation'    => $variation ? [
                    'id'           => $variation->id,
                    'uid'          => $variation->uid,
                    'aid'          => $variation->aid,
                    'color'        => $variation->color,
                    'size'         => $variation->size,
                    'regular_price'=> $variation->regular_price,
                    'sell_price'   => $variation->sell_price,
                    'stock'        => $variation->stock,
                    'images'       => $images,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Wishlist retrieved successfully.',
            'data'    => $data
        ], 200);
    }

    public function removeFromWishlist($id)
    {
        $userId = auth()->id();

        $wishlist = Wishlist::where('id', $id)
                            ->where('user_id', $userId)
                            ->first();

        if (!$wishlist) {
            return response()->json([
                'success' => false,
                'message' => 'Wishlist item not found.',
            ], 404);
        }

        $wishlist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Wishlist item removed successfully.',
        ], 200);
    }

    public function getAllWishlists()
    {
        $wishlists = Wishlist::with(['user:id,name', 'product:id,name,aid', 'variation:id,uid,aid,color,size,sell_price,images_id'])->get();

        $groupedData = [];

        foreach ($wishlists as $wishlist) {
            $userId = $wishlist->user_id;
            $userName = $wishlist->user->name;

            if (!isset($groupedData[$userId])) {
                $groupedData[$userId] = [
                    'wishlist_id' => $wishlist->id,
                    'user_id' => $userId,
                    'name' => $userName,
                    'product' => []
                ];
            }

            $productId = $wishlist->product->id;
            $productAid = $wishlist->product->aid;

            // Convert image IDs into URLs
            $images = [];
            if ($wishlist->variation && $wishlist->variation->images_id) {
                $ids = explode(',', $wishlist->variation->images_id);
                $uploads = Upload::whereIn('id', $ids)->get();
                $images = $uploads->pluck('url')->toArray();
            }

            $variationData = [
                // 'variant_id' => $wishlist->variation->id ?? null,
                'uid' => $wishlist->variation->uid ?? null,
                'aid' => $wishlist->variation->aid ?? null,
                'color' => $wishlist->variation->color ?? null,
                'size' => $wishlist->variation->size ?? null,
                'sell_price' => $wishlist->variation->sell_price ?? null,
                'images_id' => $images
            ];

            // Check if product already exists in the user's data
            $productKey = null;
            foreach ($groupedData[$userId]['product'] as $key => $prod) {
                if ($prod['product_id'] == $productId) {
                    $productKey = $key;
                    break;
                }
            }

            if ($productKey !== null) {
                $groupedData[$userId]['product'][$productKey]['variation'][] = $variationData;
            } else {
                $groupedData[$userId]['product'][] = [
                    'product_id' => $productId,
                    'poduct_name' => $wishlist->product->name,
                    'aid' => $productAid,
                    'variation' => [$variationData]
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'All wishlists retrieved successfully.',
            'data' => array_values($groupedData)
        ], 200);
    }

    // For admin
    public function getAllWishlistsForAdmin(Request $request)
    {
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $userName    = $request->input('user_name');
        $productName = $request->input('product_name');
        $sortBy      = $request->input('sort_by');
        $limit       = (int) $request->input('limit', 10);
        $offset      = (int) $request->input('offset', 0);

        $query = Wishlist::query()
            ->with([
                'user:id,name',
                'product:id,name,aid',
                'variation:id,uid,aid,color,size,sell_price,images_id'
            ]);

        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        if (!empty($userName)) {
            $query->whereHas('user', function ($q) use ($userName) {
                $q->where('name', 'like', "%{$userName}%");
            });
        }

        if (!empty($productName)) {
            $query->whereHas('product', function ($q) use ($productName) {
                $q->where('name', 'like', "%{$productName}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */

        if ($sortBy === 'price_desc') {

            $query->leftJoin('product_variations', 'wishlists.uid', '=', 'product_variations.uid')
                ->orderByDesc('product_variations.sell_price')
                ->select('wishlists.*');

        } elseif ($sortBy === 'price_asc') {

            $query->leftJoin('product_variations', 'wishlists.uid', '=', 'product_variations.uid')
                ->orderBy('product_variations.sell_price', 'asc')
                ->select('wishlists.*');

        } elseif ($sortBy === 'most_liked') {

            $query->leftJoin(
                \DB::raw('(SELECT products_id, COUNT(*) as total_likes FROM wishlists GROUP BY products_id) as likes'),
                'wishlists.products_id',
                '=',
                'likes.products_id'
            )
            ->orderByDesc('likes.total_likes')
            ->select('wishlists.*');

        } else {
            $query->orderByDesc('wishlists.id');
        }

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $total = (clone $query)->count();

        $wishlists = $query
            ->skip($offset)
            ->take($limit)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | FORMAT RESPONSE (Wishlist Wise)
        |--------------------------------------------------------------------------
        */

        $formatted = $wishlists->map(function ($wishlist) {

            $images = [];

            if ($wishlist->variation && $wishlist->variation->images_id) {
                $ids = explode(',', $wishlist->variation->images_id);
                $uploads = Upload::whereIn('id', $ids)->get();

                $images = $uploads->map(function ($upload) {
                    return url($upload->url);
                })->toArray();
            }

            return [
                'wishlist_id' => $wishlist->id,
                'user' => [
                    'id' => $wishlist->user->id ?? null,
                    'name' => $wishlist->user->name ?? null,
                ],
                'product' => [
                    'id' => $wishlist->product->id ?? null,
                    'name' => $wishlist->product->name ?? null,
                    'aid' => $wishlist->product->aid ?? null,
                ],
                'variation' => $wishlist->variation ? [
                    'uid' => $wishlist->variation->uid,
                    'aid' => $wishlist->variation->aid,
                    'color' => $wishlist->variation->color,
                    'size' => $wishlist->variation->size,
                    'sell_price' => $wishlist->variation->sell_price,
                    'images' => $images
                ] : null,
                'created_at' => $wishlist->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Wishlist data retrieved successfully.',
            'data' => $formatted,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]
        ], 200);
    }
    public function deleteWishlistByAdmin($id)
    {
        // ✅ Admin check
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can delete wishlists.'
            ], 403);
        }

        try {

            $wishlist = Wishlist::with([
                'user:id,name',
                'product:id,name,aid',
                'variation:id,uid,aid'
            ])->find($id);

            if (!$wishlist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wishlist not found.'
                ], 404);
            }

            $deletedData = [
                'wishlist_id' => $wishlist->id,
                'user_id' => $wishlist->user_id,
                'user_name' => $wishlist->user->name ?? null,
                'product_id' => $wishlist->product->id ?? null,
                'product_name' => $wishlist->product->name ?? null,
                'aid' => $wishlist->product->aid ?? null,
                'variation_uid' => $wishlist->variation->uid ?? null,
            ];

            $wishlist->delete();

            return response()->json([
                'success' => true,
                'message' => 'Wishlist deleted successfully.',
                'deleted' => $deletedData
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete wishlist.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
