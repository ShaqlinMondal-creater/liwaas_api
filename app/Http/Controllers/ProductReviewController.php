<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use App\Models\Product;
use App\Models\ProductVariations;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Helpers\ColorHelper;
use Laravel\Sanctum\PersonalAccessToken;

class ProductReviewController extends Controller
{
    // ✅ Add Review
    public function addReview(Request $request)
    {
        $validated = $request->validate([
            'user'              => 'nullable|string|max:500',
            'products_id'       => 'required|exists:products,id',
            'aid'               => 'required|string',
            'uid'               => 'required|integer',
            'total_star'        => 'required|integer|min:1|max:5',
            'comments'          => 'nullable|string',
            'upload_images'     => 'nullable',
            'upload_images.*'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $requestUser = trim($request->user ?? '');

        $userName = 'temp_user';

        if (!empty($requestUser)) {

            // Try token
            $accessToken = PersonalAccessToken::findToken($requestUser);

            if ($accessToken && $accessToken->tokenable) {
                $userName = $accessToken->tokenable->name;
            } else {
                $userName = $requestUser;
            }
        }

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

        $uploadedPaths = $this->storeReviewImages($request);

        // ✅ Save review to DB
        $review = ProductReview::create([
            'user'          => $userName,
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
                'user'       => $userName,
                'product_id' => $validated['products_id'],
                'uid'        => $validated['uid'],
                'aid'        => $validated['aid'],
                'star'       => $validated['total_star'],
                'comments'   => $validated['comments'] ?? '',
                'images'     => $this->publicReviewImages($uploadedPaths),
                'upload_images' => $this->publicReviewImages($uploadedPaths),
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
            'upload_images'     => 'nullable',
            'upload_images.*'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $uploadedPaths = $this->reviewImageList($review->upload_images);
        $newPaths = $this->storeReviewImages($request);

        if ($newPaths !== []) {
            $uploadedPaths = $newPaths;
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
                'user'       => $review->user,
                'product_id' => $review->products_id,
                'uid'        => $review->uid,
                'aid'        => $review->aid,
                'star'       => $review->total_star,
                'comments'   => $review->comments,
                'images'     => $this->publicReviewImages($review->upload_images),
                'upload_images' => $this->publicReviewImages($review->upload_images),
            ],
        ]);
    }

    // Get all review product_id wise
    public function getReviewsByProductId($productId)
    {
        $reviews = ProductReview::with(['variation:uid,color,size']) // Eager load relations
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
                'user'       => $review->user,
                'product_id' => $review->products_id,
                'uid'        => $review->uid,
                'aid'        => $review->aid,
                'variation'  => [
                    'color' => ColorHelper::get(optional($review->variation)->color),
                    'size'  => $review->variation->size ?? null,
                ],
                'star'       => $review->total_star,
                'comments'   => $review->comments,
                'images'     => $this->publicReviewImages($review->upload_images),
                'upload_images' => $this->publicReviewImages($review->upload_images),
                'created_at' => $review->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Reviews fetched successfully.',
            'data'    => $data,
        ], 200);
    }

    public function getFeaturedReviews()
    {
        $reviews = ProductReview::with(['product:id,name'])
            ->whereNotNull('comments')
            ->where('comments', '!=', '')
            ->latest()
            ->limit(8)
            ->get();

        $data = $reviews->map(function ($review) {
            return [
                'id' => $review->id,
                'user' => $review->user,
                'total_star' => $review->total_star,
                'comments' => $review->comments,
                'upload_images' => $this->publicReviewImages($review->upload_images),
                'product' => $review->product
                    ? [
                        'id' => $review->product->id,
                        'name' => $review->product->name,
                    ]
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Featured reviews fetched successfully.',
            'data' => $data,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    // Get All Review
    public function getAllReviewsWithFilters(Request $request)
    {
        $reviews = ProductReview::with(['product:id,name'])
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
                $q->where('user', 'LIKE', '%' . $request->user_name . '%'); // ✅ changed
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
                'upload_images'=> $this->publicReviewImages($review->upload_images),
                'images'       => $this->publicReviewImages($review->upload_images),
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

        foreach ($this->reviewImageList($review->upload_images) as $imgUrl) {
            $this->deleteReviewImageFile($imgUrl);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully.',
        ], 200);
    }

    /**
     * @return UploadedFile[]
     */
    private function reviewImageFiles(Request $request): array
    {
        $files = $request->file('upload_images', []);

        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            fn ($file) => $file instanceof UploadedFile && $file->isValid()
        ));
    }

    /**
     * @return string[]
     */
    private function storeReviewImages(Request $request): array
    {
        $paths = [];

        foreach ($this->reviewImageFiles($request) as $file) {
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $stored = $file->storeAs('reviews', $fileName, 'public');
            $paths[] = Storage::url($stored);
        }

        return $paths;
    }

    /**
     * @return string[]
     */
    private function reviewImageList(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } elseif (trim($raw) !== '') {
                $raw = preg_split('/\s*,\s*/', $raw) ?: [];
            } else {
                $raw = [];
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $urls = [];

        foreach ($raw as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);

            if ($item === '') {
                continue;
            }

            $urls[] = $item;
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return string[]
     */
    private function publicReviewImages(mixed $raw): array
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return array_values(array_filter(array_map(function (string $path) use ($appHost) {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                $host = parse_url($path, PHP_URL_HOST);
                $relative = parse_url($path, PHP_URL_PATH) ?: $path;

                if ($host && $appHost && strcasecmp((string) $host, (string) $appHost) !== 0) {
                    return url($relative);
                }

                return $path;
            }

            return url('/' . ltrim($path, '/'));
        }, $this->reviewImageList($raw))));
    }

    private function deleteReviewImageFile(string $imgUrl): void
    {
        $path = parse_url($imgUrl, PHP_URL_PATH) ?: $imgUrl;
        $path = ltrim((string) $path, '/');

        if (str_starts_with($path, 'storage/')) {
            Storage::disk('public')->delete(substr($path, strlen('storage/')));
        }

        $legacy = public_path($path);
        if (is_file($legacy)) {
            @unlink($legacy);
        }
    }

}
