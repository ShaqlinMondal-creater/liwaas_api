<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use App\Models\Product;
use App\Models\ProductVariations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\DB;

class ProductReviewController extends Controller
{
    // ✅ Add Review
    public function addReview(Request $request)
    {
        $validated = $request->validate([
            'products_id'       => 'required|exists:products,id',
            'aid'               => 'required|string',
            'uid'               => 'required|integer',
            'total_star'        => 'required|integer|min:1|max:5',
            'comments'          => 'nullable|string',
            'upload_images.*'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048', // max 2MB each
        ]);

        $userId = auth()->id();

        // ✅ Check if the product variation exists
        $variation = ProductVariations::where('uid', $validated['uid'])
            ->where('aid', $validated['aid'])
            ->first();

        if (!$variation) {
            return response()->json([
                'success' => false,
                'message' => 'Product variation not found.',
            ], 404);
        }

        // ✅ Handle image uploads
        $uploadedPaths = [];

        if ($request->hasFile('upload_images')) {
            foreach ($request->file('upload_images') as $image) {
                $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/reviews'), $filename);
                $uploadedPaths[] = asset('uploads/reviews/' . $filename);
            }
        }

        // ✅ Save review to DB
        $review = ProductReview::create([
            'user_id'       => $userId,
            'products_id'   => $validated['products_id'],
            'aid'           => $validated['aid'],
            'uid'           => $validated['uid'],
            'total_star'    => $validated['total_star'],
            'comments'      => $validated['comments'] ?? '',
            'upload_images' => $uploadedPaths, // JSON cast
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data'    => [
                'id'         => $review->id,
                'user_id'    => $userId,
                'product_id' => $validated['products_id'],
                'uid'        => $validated['uid'],
                'aid'        => $validated['aid'],
                'star'       => $validated['total_star'],
                'comments'   => $validated['comments'] ?? '',
                'images'     => $uploadedPaths,
            ],
        ], 201);
    }
   
    // Update Review
    public function updateReview(Request $request, $id)
    {
        $review = ProductReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        // Validate incoming fields
        $validated = $request->validate([
            'total_star'        => 'nullable|integer|min:1|max:5',
            'comments'          => 'nullable|string',
            'upload_images.*'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Handle new image uploads (replace old)
        $uploadedPaths = $review->upload_images ?? [];

        if ($request->hasFile('upload_images')) {
            // Delete old images (optional, if needed to clean up files)

            // Replace all images
            $uploadedPaths = [];
            foreach ($request->file('upload_images') as $image) {
                $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/reviews'), $filename);
                $uploadedPaths[] = asset('uploads/reviews/' . $filename);
            }
        }

        // Update review
        $review->update([
            'total_star'    => $validated['total_star'] ?? $review->total_star,
            'comments'      => $validated['comments'] ?? $review->comments,
            'upload_images' => $uploadedPaths,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully.',
            'data'    => [
                'id'         => $review->id,
                'user_id'    => $review->user_id,
                'product_id' => $review->products_id,
                'uid'        => $review->uid,
                'aid'        => $review->aid,
                'star'       => $review->total_star,
                'comments'   => $review->comments,
                'images'     => $review->upload_images,
            ],
        ]);
    }

    // Get all review product_id wise
    public function getReviewsByProductId($productId)
    {
        $reviews = ProductReview::with(['user:id,name', 'variation:uid,color,size']) // Eager load relations
            ->where('products_id', $productId)
            ->orderByDesc('id')
            ->get();

        if ($reviews->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No reviews found for this product.',
            ], 404);
        }

        $data = $reviews->map(function ($review) {
            return [
                'id'         => $review->id,
                'user_id'    => $review->user_id,
                'user_name'  => $review->user->name ?? null,
                'product_id' => $review->products_id,
                'uid'        => $review->uid,
                'aid'        => $review->aid,
                'variation'  => [
                    'color' => $review->variation->color ?? null,
                    'size'  => $review->variation->size ?? null,
                ],
                'star'       => $review->total_star,
                'comments'   => $review->comments,
                'images'     => $review->upload_images ?? [],
                'created_at' => $review->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Reviews fetched successfully.',
            'data'    => $data,
        ], 200);
    }

    // Get All Review
    public function getAllReviewsWithFilters(Request $request)
    {
        $reviews = ProductReview::with(['user:id,name', 'product:id,name'])
            ->when($request->product_name, function ($q) use ($request) {
                $q->whereHas('product', function ($q2) use ($request) {
                    $q2->where('name', 'LIKE', '%' . $request->product_name . '%');
                });
            })
            ->when($request->aid, function ($q) use ($request) {
                $q->where('aid', 'LIKE', '%' . $request->aid . '%');
            })
            ->when($request->uid, function ($q) use ($request) {
                $q->where('uid', $request->uid);
            })
            ->when($request->user_name, function ($q) use ($request) {
                $q->whereHas('user', function ($q2) use ($request) {
                    $q2->where('name', 'LIKE', '%' . $request->user_name . '%');
                });
            })
            ->when($request->total_star, function ($q) use ($request) {
                $q->where('total_star', $request->total_star);
            })
            ->latest()
            ->get();

        // ✅ Format response without created_at & updated_at
        $filtered = $reviews->map(function ($review) {
            return [
                'id'           => $review->id,
                'user_id'      => $review->user_id,
                'products_id'  => $review->products_id,
                'aid'          => $review->aid,
                'uid'          => $review->uid,
                'total_star'   => $review->total_star,
                'comments'     => $review->comments,
                'upload_images'=> $review->upload_images,
                'user'         => $review->user,
                'product'      => $review->product,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Filtered reviews retrieved successfully.',
            'data' => $filtered
        ], 200);
    }

    // Delete Review
    public function deleteReview($id)
    {
        $review = ProductReview::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        // Optionally delete associated images from storage
        if (is_array($review->upload_images)) {
            foreach ($review->upload_images as $imgUrl) {
                $path = public_path(str_replace(url('/'), '', $imgUrl));
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully.',
        ], 200);
    }

}
