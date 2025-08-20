<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\ProductVariations;
use App\Models\Upload;
use Illuminate\Support\Str;

class CartController extends Controller
{
    //✅ Add product to cart
    public function createCart(Request $request)
    {
        // ✅ Step 1: Validate input
        $validated = $request->validate([
            'products_id' => 'required|exists:products,id',
            'aid'         => 'required|string',
            'uid'         => 'required|integer',
            'quantity'    => 'required|integer|min:1',
            'temp_id'     => 'nullable|string',
        ]);

        // ✅ Step 2: Try to extract auth user from token manually (if exists)
        $user = null;
        if ($request->bearerToken()) {
            $user = auth('sanctum')->user(); // Only resolves if token valid
        }

        if ($user) {
            $userId = $user->id;
            $isGuest = false;
        } elseif (!empty($validated['temp_id'])) {
            $userId = $validated['temp_id']; // Existing guest
            $isGuest = true;
        } else {
            $userId = 'temp_' . Str::random(12); // New guest
            $isGuest = true;
        }

        // ✅ Step 3: Get product
        $product = Product::find($validated['products_id']);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        // ✅ Step 4: Get variation
        $variation = ProductVariations::where('uid', $validated['uid'])
                                    ->where('aid', $validated['aid'])
                                    ->first();

        if (!$variation) {
            return response()->json([
                'success' => false,
                'message' => 'Variation not found.',
            ], 404);
        }

        // ✅ Step 5: Check for existing cart item
        $existingCart = Cart::where('user_id', $userId)
                            ->where('products_id', $validated['products_id'])
                            ->where('uid', $validated['uid'])
                            ->first();

        if ($existingCart) {
            $existingCart->quantity += $validated['quantity'];
            $existingCart->total_price = $variation->sell_price * $existingCart->quantity;
            $existingCart->save();

            return response()->json([
                'success' => true,
                'message' => 'Cart updated successfully.',
                'data'    => $existingCart,
                'temp_id' => $isGuest ? $userId : null,
            ], 200);
        }

        // ✅ Step 6: Create cart item
        $cart = Cart::create([
            'user_id'       => $userId,
            'products_id'   => $validated['products_id'],
            'aid'           => $validated['aid'],
            'uid'           => $validated['uid'],
            'regular_price' => $variation->regular_price,
            'sell_price'    => $variation->sell_price,
            'quantity'      => $validated['quantity'],
            'total_price'   => $variation->sell_price * $validated['quantity'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cart created successfully.',
            'data'    => $cart,
            'temp_id' => $isGuest ? $userId : null,
        ], 201);
    }

    // Update cart according to the cart id
    public function updateCart(Request $request, $id)
    {
        // Validate only quantity
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        // Find cart by ID
        $cart = Cart::find($id);

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found.',
            ], 404);
        }

        // Update quantity and total price
        $cart->quantity = $validated['quantity'];
        $cart->total_price = $cart->sell_price * $validated['quantity'];
        $cart->save();

        return response()->json([
            'success' => true,
            'message' => 'Cart updated successfully.',
            'data'    => $cart,
        ], 200);
    }

    // Get Users Cart Data
    public function getUserCart(Request $request)
    {
        // ✅ Try to get authenticated user
        $user = null;
        if ($request->bearerToken()) {
            $user = auth('sanctum')->user(); // Only resolves if token is valid
        }

        if ($user) {
            $userId = $user->id;
        } else {
            $request->validate([
                'temp_id' => 'required|string',
            ]);
            $userId = $request->input('temp_id');
        }

        // ✅ Fetch cart with product & variation
        $carts = Cart::with(['product:id,name', 'variation:id,uid,aid,color,size,images_id'])
                    ->where('user_id', $userId)
                    ->get();

        if ($carts->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Your cart is empty.',
                'data'    => []
            ], 200);
        }

        // ✅ Format response
        $formatted = $carts->map(function ($cart) {
            $imageUrls = [];

            // Resolve images
            $imagesId = optional($cart->variation)->images_id;
            if ($imagesId) {
                $imageIdsArray = array_filter(explode(',', $imagesId));
                $uploads = Upload::whereIn('id', $imageIdsArray)->get(['id', 'url']);
                // $imageUrls = $uploads->pluck('url')->map(fn($url) => asset('uploads/' . $url))->toArray();
                $imageUrls = $uploads->pluck('url')->map(function ($url) {
                    // If already a full URL, return as is
                    if (preg_match('/^http(s)?:\/\//', $url)) {
                        return $url;
                    }
                    // Otherwise prepend your uploads path
                    return asset('uploads/' . ltrim($url, '/'));
                })->toArray();

            }

            return [
                'cart_id'      => $cart->id,
                'product_id'   => $cart->products_id,
                'product_name' => optional($cart->product)->name,
                'quantity'     => $cart->quantity,
                'sell_price'   => (float) $cart->sell_price,
                'total_price'  => (float) $cart->total_price,
                'variation'    => [
                    'uid'    => $cart->uid,
                    'aid'    => $cart->aid,
                    'color'  => optional($cart->variation)->color,
                    'size'   => optional($cart->variation)->size,
                    'images' => $imageUrls
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Cart items retrieved successfully.',
            'data'    => $formatted
        ], 200);
    }

    // Delete Cart according to cart id
    public function deleteCart($cartId)
    {
        // Find the cart item by ID
        $cart = Cart::find($cartId);

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found.',
            ], 404);
        }

        // Delete the cart item
        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart item deleted successfully.',
        ], 200);
    }

    public function getAllCartsForAdmin(Request $request)
    {
        // Optional: Admin check
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can access all carts.'
            ], 403);
        }

        // Get limit and offset from request body (JSON)
        $limit = $request->input('limit', 10);    // default: 10
        $offset = $request->input('offset', 0);   // default: 0

        // Prepare base query
        $query = Cart::with(['user', 'product', 'variation']);

        // Get total before pagination
        $total = $query->count();

        // Apply pagination
        $carts = $query->skip($offset)->take($limit)->get();

        // Format cart data
        $formatted = $carts->map(function ($cart) {
            $variation = $cart->variation;
            $imageUrls = [];

            if ($variation && $variation->images_id) {
                $ids = explode(',', $variation->images_id);
                $uploads = \App\Models\Upload::whereIn('id', $ids)->get();

                $imageUrls = $uploads->map(fn($upload) =>
                    $upload->url ?? asset('uploads/' . $upload->path)
                )->toArray();
            }

            return [
                'id' => $cart->id,
                'user_id' => $cart->user_id,
                'cart' => [
                    'user_name' => $cart->user->name ?? null,
                    'product_id' => $cart->products_id,
                    'variation_uid' => $cart->uid,
                    'variation_aid' => $cart->aid,
                    'quantity' => $cart->quantity,
                    'regular_price' => $cart->regular_price,
                    'sell_price' => $cart->sell_price,
                    'total_price' => $cart->total_price,
                    'created_at' => $cart->created_at,
                    'updated_at' => $cart->updated_at,
                    'product' => optional($cart->product)->only(['id', 'name', 'aid']),
                    'variation' => $variation ? [
                        'id' => $variation->id,
                        'uid' => $variation->uid,
                        'aid' => $variation->aid,
                        'color' => $variation->color,
                        'size' => $variation->size,
                        'regular_price' => $variation->regular_price,
                        'sell_price' => $variation->sell_price,
                        'stock' => $variation->stock,
                        'images' => $imageUrls
                    ] : null,
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'All carts retrieved successfully.',
            'data' => $formatted,
            'meta' => [
                'total' => $total,
                'limit' => (int) $limit,
                'offset' => (int) $offset
            ]
        ], 200);
    }


}
