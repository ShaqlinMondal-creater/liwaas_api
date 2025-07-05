<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\ProductVariations;
use App\Models\Upload;

class CartController extends Controller
{
    // ✅ Add product to cart
    // public function createCart(Request $request)
    // {
    //     // ✅ Step 1: Validate request
    //     $validated = $request->validate([
    //         'products_id'   => 'required|exists:products,id',
    //         'aid'           => 'required|string',
    //         'uid'           => 'required|integer',
    //         'regular_price' => 'required|numeric',
    //         'sell_price'    => 'required|numeric',
    //         'quantity'      => 'required|integer|min:1',
    //     ]);

    //     // ✅ Step 2: Get authenticated user ID
    //     $userId = auth()->id();

    //     // ✅ Step 3: Check product existence
    //     $product = Product::find($validated['products_id']);
    //     if (!$product) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Product not found.',
    //         ], 404);
    //     }

    //     // ✅ Step 4: Check variation existence with UID and AID
    //     $variation = ProductVariations::where('uid', $validated['uid'])
    //                                 ->where('aid', $validated['aid'])
    //                                 ->first();

    //     if (!$variation) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Variation not found for the given UID and AID.',
    //         ], 404);
    //     }

    //     // ✅ Step 5: Check if product + variation already exists in user's cart
    //     $existingCart = Cart::where('user_id', $userId)
    //                         ->where('products_id', $validated['products_id'])
    //                         ->where('uid', $validated['uid'])
    //                         ->first();

    //     if ($existingCart) {
    //         // ✅ Update quantity and total_price
    //         $existingCart->quantity += $validated['quantity'];
    //         $existingCart->total_price = $existingCart->sell_price * $existingCart->quantity;
    //         $existingCart->save();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Cart updated successfully.',
    //             'data'    => $existingCart
    //         ], 200);
    //     }

    //     // ✅ Step 6: Create new cart
    //     $totalPrice = $validated['sell_price'] * $validated['quantity'];

    //     $cart = Cart::create([
    //         'user_id'       => $userId,
    //         'products_id'   => $validated['products_id'],
    //         'aid'           => $validated['aid'],
    //         'uid'           => $validated['uid'],
    //         'regular_price' => $validated['regular_price'],
    //         'sell_price'    => $validated['sell_price'],
    //         'quantity'      => $validated['quantity'],
    //         'total_price'   => $totalPrice,
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Cart created successfully.',
    //         'data'    => $cart
    //     ], 201);
    // }
    public function createCart(Request $request)
    {
        // ✅ Step 1: Validate request
        $validated = $request->validate([
            'products_id' => 'required|exists:products,id',
            'aid'         => 'required|string',
            'uid'         => 'required|integer',
            'quantity'    => 'required|integer|min:1',
        ]);

        // ✅ Step 2: Get authenticated user ID
        $userId = auth()->id();

        // ✅ Step 3: Verify product existence
        $product = Product::find($validated['products_id']);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        // ✅ Step 4: Fetch product variation using UID and AID
        $variation = ProductVariations::where('uid', $validated['uid'])
                                    ->where('aid', $validated['aid'])
                                    ->first();

        if (!$variation) {
            return response()->json([
                'success' => false,
                'message' => 'Variation not found for the given UID and AID.',
            ], 404);
        }

        // ✅ Step 5: Check if the cart entry already exists
        $existingCart = Cart::where('user_id', $userId)
                            ->where('products_id', $validated['products_id'])
                            ->where('uid', $validated['uid'])
                            ->first();

        if ($existingCart) {
            // ✅ Update quantity and total price
            $existingCart->quantity += $validated['quantity'];
            $existingCart->total_price = $variation->sell_price * $existingCart->quantity;
            $existingCart->save();

            return response()->json([
                'success' => true,
                'message' => 'Cart updated successfully.',
                'data'    => $existingCart
            ], 200);
        }

        // ✅ Step 6: Create a new cart item
        $totalPrice = $variation->sell_price * $validated['quantity'];

        $cart = Cart::create([
            'user_id'       => $userId,
            'products_id'   => $validated['products_id'],
            'aid'           => $validated['aid'],
            'uid'           => $validated['uid'],
            'regular_price' => $variation->regular_price,
            'sell_price'    => $variation->sell_price,
            'quantity'      => $validated['quantity'],
            'total_price'   => $totalPrice,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cart created successfully.',
            'data'    => $cart
        ], 201);
    }

    public function updateCart(Request $request)
    {
        $validated = $request->validate([
            'products_id' => 'required|exists:products,id',
            'uid'         => 'required|integer',
            'quantity'    => 'required|integer|min:1',
        ]);

        $userId = auth()->id();

        $cart = Cart::where('user_id', $userId)
            ->where('products_id', $validated['products_id'])
            ->where('uid', $validated['uid'])
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found. Create it first.',
            ], 404);
        }

        $cart->quantity = $validated['quantity'];
        $cart->total_price = $cart->sell_price * $validated['quantity'];
        $cart->save();

        return response()->json([
            'success' => true,
            'message' => 'Cart updated successfully.',
            'data'    => $cart,
        ], 200);
    }

    public function getUserCart()
    {
        $userId = auth()->id();

        // Fetch cart with necessary relationships
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

        // Format response
        $formatted = $carts->map(function ($cart) {
            $imageUrls = [];

            // Parse and resolve image URLs from variation's images_id
            $imagesId = optional($cart->variation)->images_id;
            if ($imagesId) {
                $imageIdsArray = array_filter(explode(',', $imagesId));

                // Fetch uploads with matching IDs
                $uploads = Upload::whereIn('id', $imageIdsArray)->get(['id', 'url']);

                // Extract URLs
                $imageUrls = $uploads->pluck('url')->toArray();
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

    public function deleteCart($cartId)
    {
        $userId = auth()->id(); // Get the authenticated user ID

        // Find the cart item that belongs to the current user
        $cart = Cart::where('id', $cartId)
                    ->where('user_id', $userId)
                    ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found or does not belong to the user.',
            ], 404);
        }

        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart item deleted successfully.',
        ], 200);
    }

    // public function getAllCartsForAdmin()
    // {
    //     // Optional: Admin check
    //     if (!auth()->user() || auth()->user()->role !== 'admin') {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized. Only admins can access all carts.'
    //         ], 403);
    //     }

    //     // Fetch all carts with relationships
    //     $carts = Cart::with(['user', 'product', 'variation'])->get();

    //     // Map the response
    //     $formatted = $carts->map(function ($cart) {
    //         $variation = $cart->variation;
    //         $imageUrls = [];

    //         if ($variation && $variation->images_id) {
    //             $ids = explode(',', $variation->images_id);
    //             $uploads = \App\Models\Upload::whereIn('id', $ids)->get();

    //             $imageUrls = $uploads->map(function ($upload) {
    //                 return $upload->url ?? asset('uploads/' . $upload->path);
    //             })->toArray();
    //         }

    //         return [
    //             'id' => $cart->id,
    //             'user_id' => $cart->user_id,
    //             'cart' => $cart ? [
    //                 'user_name' => $cart->user->name ?? null,
    //                 'product_id' => $cart->products_id,
    //                 'variation_uid' => $cart->uid,
    //                 'variation_aid' => $cart->aid,
    //                 'quantity' => $cart->quantity,
    //                 'regular_price' => $cart->regular_price,
    //                 'sell_price' => $cart->sell_price,
    //                 'total_price' => $cart->total_price,
    //                 'created_at' => $cart->created_at,
    //                 'updated_at' => $cart->updated_at,
    //                 'product' => $cart->product ? [
    //                     'id' => $cart->product->id,
    //                     'name' => $cart->product->name,
    //                     'aid' => $cart->product->aid
    //                 ] : null,
    //                 'variation' => $variation ? [
    //                     'id' => $variation->id,
    //                     'uid' => $variation->uid,
    //                     'aid' => $variation->aid,
    //                     'color' => $variation->color,
    //                     'size' => $variation->size,
    //                     'regular_price' => $variation->regular_price,
    //                     'sell_price' => $variation->sell_price,
    //                     'stock' => $variation->stock,
    //                     'images' => $imageUrls
    //                 ] : null,
    //             ] : null
    //         ];
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'All carts retrieved successfully.',
    //         'data' => $formatted
    //     ], 200);
    // }

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
