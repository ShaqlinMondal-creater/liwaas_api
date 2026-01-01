<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductSpecModel;
use App\Models\ProductVariations;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Upload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // Add product simple and variation    
    public function addProduct(Request $request)
    {
        try {
            $validated = $request->validate([
                'aid' => 'required|string',
                'name' => 'required|string',
                'brand' => 'required|integer',
                'category' => 'required|integer',
                'slug' => 'nullable|string',
                'description' => 'nullable|string',
                'specification' => 'nullable|string',
                'gender' => 'required|in:male,female,unisex',
                'cod' => 'nullable|in:available,not available',
                'shipping' => 'nullable|in:available,not available',
                'ratings' => 'nullable|numeric',
                'keyword' => 'nullable|string',
                'image_url' => 'nullable|string',
                'upload_id' => 'nullable|string',
                'product_status' => 'nullable|in:active,inactive',
                'added_by' => 'nullable|string',
                'custom_design' => 'nullable|in:available,not available',
                'variations' => 'nullable|array',
                'variations.*.uid' => 'required_with:variations|numeric|distinct|unique:product_variations,uid',
                'variations.*.regular_price' => 'required_with:variations|numeric',
                'variations.*.sale_price' => 'required_with:variations|numeric',
                'variations.*.size' => 'required_with:variations|string',
                'variations.*.color' => 'required_with:variations|string',
                'variations.*.stock' => 'required_with:variations|integer',
                'uid' => 'required_without:variations|numeric|unique:product_variations,uid',
                'regular_price' => 'required_without:variations|numeric',
                'sale_price' => 'required_without:variations|numeric',
                'size' => 'required_without:variations|string',
                'color' => 'required_without:variations|string',
                'stock' => 'required_without:variations|integer',
            ]);

            // ✅ Validate brand & category
            if (!Brand::find($validated['brand'])) {
                return response()->json(['success' => false, 'message' => 'Invalid brand ID.'], 400);
            }

            if (!Category::find($validated['category'])) {
                return response()->json(['success' => false, 'message' => 'Invalid category ID.'], 400);
            }

            // ✅ Check if AID already exists
            $existingProduct = Product::where('aid', $validated['aid'])->first();

            if ($existingProduct) {
                // ✅ It's a variant for existing product (do not update product or slug)
                if (!empty($validated['variations'])) {
                    foreach ($validated['variations'] as $variation) {
                        ProductVariations::create([
                            'aid' => $validated['aid'],
                            'uid' => $variation['uid'],
                            'regular_price' => $variation['regular_price'],
                            'sell_price' => $variation['sale_price'],
                            'currency' => 'INR',
                            'gst' => 18,
                            'stock' => $variation['stock'],
                            'color' => $variation['color'],
                            'size' => $variation['size'],
                        ]);
                    }
                } else {
                    ProductVariations::create([
                        'aid' => $validated['aid'],
                        'uid' => $validated['uid'],
                        'regular_price' => $validated['regular_price'],
                        'sell_price' => $validated['sale_price'],
                        'currency' => 'INR',
                        'gst' => 18,
                        'stock' => $validated['stock'],
                        'color' => $validated['color'],
                        'size' => $validated['size'],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Variation added to existing product.',
                    'product_id' => $existingProduct->id
                ], 200);
            }

            // ✅ Slug creation
            $baseSlug = $validated['slug'] ?? \Str::slug($validated['name']);
            $slug = $baseSlug;

            while (Product::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . strtolower(\Str::random(6));
            }

            // ✅ Create new product
            $product = Product::create([
                'aid' => $validated['aid'],
                'name' => $validated['name'],
                'brand_id' => $validated['brand'],
                'category_id' => $validated['category'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'specification' => $validated['specification'] ?? null,
                'gender' => $validated['gender'],
                'cod' => $validated['cod'] ?? 'available',
                'shipping' => $validated['shipping'] ?? 'available',
                'ratings' => $validated['ratings'] ?? 0,
                'keyword' => $validated['keyword'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
                'upload_id' => $validated['upload_id'] ?? null,
                'product_status' => $validated['product_status'] ?? 'active',
                'added_by' => $validated['added_by'] ?? 'admin',
                'custom_design' => $validated['custom_design'] ?? 'not available'
            ]);

            // ✅ Add its first variation(s)
            if (!empty($validated['variations'])) {
                foreach ($validated['variations'] as $variation) {
                    ProductVariations::create([
                        'aid' => $validated['aid'],
                        'uid' => $variation['uid'],
                        'regular_price' => $variation['regular_price'],
                        'sell_price' => $variation['sale_price'],
                        'currency' => 'INR',
                        'gst' => 18,
                        'stock' => $variation['stock'],
                        'color' => $variation['color'],
                        'size' => $variation['size'],
                    ]);
                }
            } else {
                ProductVariations::create([
                    'aid' => $validated['aid'],
                    'uid' => $validated['uid'],
                    'regular_price' => $validated['regular_price'],
                    'sell_price' => $validated['sale_price'],
                    'currency' => 'INR',
                    'gst' => 18,
                    'stock' => $validated['stock'],
                    'color' => $validated['color'],
                    'size' => $validated['size'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'New product created successfully.',
                'product_id' => $product->id
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get all product with Filters
    public function getAllProducts(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            $query = Product::with([
                'brand:id,name,logo',
                'category:id,name,logo',
                'variations:aid,uid,color,size,regular_price,sell_price,currency,gst,stock,images_id'
            ]);

            // Filters
            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $search = $request->search;
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('keyword', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('aid', 'like', "%{$search}%");
                })->orWhereHas('variations', function ($q) use ($request) {
                    $q->where('uid', 'like', '%' . $request->search . '%');
                });
            }


            if ($request->filled('aid')) {
                $query->where('aid', $request->aid);
            }

            if ($request->filled('brand')) {
                $query->where('brand_id', $request->brand);
            }

            if ($request->filled('category')) {
                $query->where('category_id', $request->category);
            }

            if ($request->filled('size')) {
                $query->whereHas('variations', function ($q) use ($request) {
                    $q->where('size', $request->size);
                });
            }

            if ($request->filled('color')) {
                $query->whereHas('variations', function ($q) use ($request) {
                    $q->where('color', $request->color);
                });
            }

            $total = $query->count();
            $products = $query->skip($offset)->take($limit)->get();

            // Format output
            $filtered = $products->map(function ($product) {
                $arr = $product->toArray();

                // Resolve product-level images from upload_id
                $productImageIds = array_filter(explode(',', $product->upload_id ?? ''));
                $arr['upload'] = \App\Models\Upload::whereIn('id', $productImageIds)->get(['id', 'url', 'file_name']);

                // Resolve variation images
                $arr['variations'] = collect($product->variations)->map(function ($variation) {
                    $variationArr = $variation->toArray();

                    $imageIds = array_filter(explode(',', $variation->images_id ?? ''));
                    $variationArr['images'] = \App\Models\Upload::whereIn('id', $imageIds)->get(['id', 'url', 'file_name']);

                    return $variationArr;
                });

                unset($arr['created_at'], $arr['updated_at'], $arr['brand_id'], $arr['category_id']);
                return $arr;
            });

            return response()->json([
                'success' => true,
                'message' => 'Products fetched successfully.',
                'data' => $filtered,
                'total' => $total,
                'limit' => (int) $limit,
                'offset' => (int) $offset
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching products.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get Product by slug
    public function getProductsBySlug($slug)
    {
        try {
            $product = Product::with([
                'brand:id,name,logo',
                'category:id,name,logo',
                'variations' => function ($q) {
                    $q->select('aid', 'uid', 'color', 'size', 'regular_price', 'sell_price', 'currency', 'gst', 'stock', 'images_id');
                }
            ])->where('slug', trim($slug))->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found with the given slug.',
                    'slug_received' => $slug,
                ], 404);
            }

            $data = $product->toArray();
            unset($data['created_at'], $data['updated_at'], $data['brand_id'], $data['category_id']);

            // Attach variation images manually
            foreach ($data['variations'] as &$variation) {
                $imageIds = array_filter(explode(',', $variation['images_id']));
                $variation['images'] = Upload::whereIn('id', $imageIds)->get(['id', 'url', 'file_name']);

                // ✅ Product Specs (NEW)
                $variation['specs'] = ProductSpecModel::where('uid', $variation['uid'])
                    ->get(['id', 'spec_name', 'spec_value']);
            }

            // Attach product uploads manually using upload_id
            $uploadIds = array_filter(explode(',', $product->upload_id));
            $data['upload'] = Upload::whereIn('id', $uploadIds)->get(['id', 'url', 'file_name']);

            return response()->json([
                'success' => true,
                'message' => 'Product fetched successfully.',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching product.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get Product by AID
    public function getProductsByAid($aid)
    {
        try {
            $product = Product::with([
                'brand:id,name,logo',
                'category:id,name,logo',
                'variations' => function ($q) {
                    $q->select('aid', 'uid', 'color', 'size', 'regular_price', 'sell_price', 'currency', 'gst', 'stock', 'images_id');
                }
            ])->where('aid', trim($aid))->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found with the given AID.',
                    'aid_received' => $aid,
                ], 404);
            }

            $data = $product->toArray();
            unset($data['created_at'], $data['updated_at'], $data['brand_id'], $data['category_id']);

            // Attach variation images
            foreach ($data['variations'] as &$variation) {
                $imageIds = array_filter(explode(',', $variation['images_id']));
                $variation['images'] = Upload::whereIn('id', $imageIds)->get(['id', 'url', 'file_name']);

                // ✅ Product Specs (NEW)
                $variation['specs'] = ProductSpecModel::where('uid', $variation['uid'])
                    ->get(['id', 'spec_name', 'spec_value']);
            }

            // Attach main uploads
            $uploadIds = array_filter(explode(',', $product->upload_id));
            $data['upload'] = Upload::whereIn('id', $uploadIds)->get(['id', 'url', 'file_name']);

            return response()->json([
                'success' => true,
                'message' => 'Product fetched successfully by AID.',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching product by AID.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get Product by UID
    public function getProductsByUid($uid)
    {
        try {
            // 1️⃣ Find variation by UID
            $variation = ProductVariations::where('uid', trim($uid))->first();

            if (!$variation) {
                return response()->json([
                    'success' => false,
                    'message' => 'No variation found with the given UID.',
                    'uid_received' => $uid,
                ], 404);
            }

            // 2️⃣ Fetch related product via AID
            $product = Product::with([
                'brand:id,name,logo',
                'category:id,name,logo'
            ])->where('aid', $variation->aid)->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found for this variation UID.',
                ], 404);
            }

            // 3️⃣ Convert product to array and clean up fields
            $data = $product->toArray();
            unset($data['created_at'], $data['updated_at'], $data['brand_id'], $data['category_id']);

            // 4️⃣ Get all variations under same AID
            $variations = ProductVariations::where('aid', $variation->aid)
                ->get(['aid', 'uid', 'color', 'size', 'regular_price', 'sell_price', 'currency', 'gst', 'stock', 'images_id'])
                ->map(function ($v) {
                    $imageIds = array_filter(explode(',', $v->images_id));
                    $v->images = Upload::whereIn('id', $imageIds)->get(['id', 'url', 'file_name']);

                    // ✅ Product Specs (NEW)
                    $v->specs = ProductSpecModel::where('uid', $v->uid)
                        ->get(['id', 'spec_name', 'spec_value']);

                    return $v;
                });

            // 5️⃣ Sort variations so selected UID is first
            $variations = $variations->sortByDesc(function ($v) use ($uid) {
                return $v->uid === $uid;
            })->values();

            // 6️⃣ Attach uploads for product itself
            $uploadIds = array_filter(explode(',', $product->upload_id));
            $data['upload'] = Upload::whereIn('id', $uploadIds)->get(['id', 'url', 'file_name']);

            // 7️⃣ Final structured response
            $data['selected_variation'] = $variations->firstWhere('uid', $uid);
            $data['variations'] = $variations;
            

            return response()->json([
                'success' => true,
                'message' => 'Product fetched successfully by UID.',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching product by UID.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete Product Variations
    public function deleteVariation($uid)
    {
        try {
            $variation = ProductVariations::where('uid', $uid)->first();

            if (!$variation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Variation not found.',
                    'uid_received' => $uid
                ], 404);
            }

            // Delete images related to this variation
            $imageIds = array_filter(explode(',', $variation->images_id ?? ''));

            foreach ($imageIds as $id) {
                $upload = Upload::find($id);
                if ($upload) {
                    $filePath = public_path($upload->path);
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                    $upload->delete();
                }
            }

            // Remove these image ids from products table upload_id field
            $product = Product::where('aid', $variation->aid)->first();
            if ($product && $product->upload_id) {
                $existingIds = array_filter(explode(',', $product->upload_id));
                $remainingIds = array_diff($existingIds, $imageIds);
                $product->upload_id = implode(',', $remainingIds);
                $product->save();
            }

            // Delete the variation
            $variation->delete();

            // Check if this was the only variation
            $remainingVariations = ProductVariations::where('aid', $variation->aid)->count();

            if ($remainingVariations === 0 && $product) {
                // Delete product images
                $productImageIds = array_filter(explode(',', $product->upload_id ?? ''));
                foreach ($productImageIds as $id) {
                    $upload = Upload::find($id);
                    if ($upload) {
                        $filePath = public_path($upload->path);
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                        $upload->delete();
                    }
                }

                // Finally, delete the product
                $product->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Variation deleted successfully.',
                'uid_deleted' => $uid
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete variation.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete Product with their Variations
    public function deleteProduct($aid)
    {
        try {
            // Find product by aid
            $product = Product::where('aid', $aid)->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                    'aid_received' => $aid
                ], 404);
            }

            $allUploadIds = [];

            // ✅ Collect upload_ids from product
            if (!empty($product->upload_id)) {
                $productUploadIds = array_map('trim', explode(',', $product->upload_id));
                $allUploadIds = array_merge($allUploadIds, $productUploadIds);
            }

            // ✅ Collect images_id from all variations
            $variations = ProductVariations::where('aid', $aid)->get();
            foreach ($variations as $variation) {
                if (!empty($variation->images_id)) {
                    $variationUploadIds = array_map('trim', explode(',', $variation->images_id));
                    $allUploadIds = array_merge($allUploadIds, $variationUploadIds);
                }
            }

            // ✅ Remove duplicates
            $allUploadIds = array_unique($allUploadIds);

            // ✅ Delete all related uploads from folder and DB
            if (!empty($allUploadIds)) {
                $uploads = Upload::whereIn('id', $allUploadIds)->get();

                foreach ($uploads as $upload) {
                    $filePath = public_path('uploads/products/' . $upload->file_name);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $upload->delete();
                }
            }

            // ✅ Delete variations
            ProductVariations::where('aid', $aid)->delete();

            // ✅ Finally, delete the product
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product, variations, and all related images deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting product.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update Product
    public function updateProduct(Request $request)
    {
        $request->validate([
            'aid' => 'required|string|exists:products,aid',
            'uid' => 'required|integer|exists:product_variations,uid',
            'name' => 'nullable|string',
            'brand_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'specification' => 'nullable|string',
            'gender' => 'nullable|string',
            'cod' => 'nullable|string',
            'shipping' => 'nullable|string',
            'keyword' => 'nullable|string',
            'custom_design' => 'nullable|string',
            'regular_price' => 'nullable|numeric',
            'sell_price' => 'nullable|numeric',
            'stock' => 'nullable|integer',
            'color' => 'nullable|string',
            'size' => 'nullable|string',
            'upload_image.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240'
        ]);

        try {
            $product = Product::where('aid', $request->aid)->first();
            $variation = ProductVariations::where('uid', $request->uid)->first();

            if (!$product || !$variation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product or Variation not found.'
                ], 404);
            }

            // ✅ Update product fields
            $product->update($request->only([
                'name', 'brand_id', 'category_id', 'description', 'specification', 'gender',
                'cod', 'shipping', 'keyword', 'custom_design'
            ]));

            // ✅ Update variation fields
            $variation->update($request->only([
                'regular_price', 'sell_price', 'stock', 'color', 'size'
            ]));

            // ✅ Handle new uploaded images (optional)
            $uploadedImages = $request->file('upload_image');
            if ($uploadedImages && is_array($uploadedImages)) {
                $destination = public_path('uploads/products');
                if (!File::exists($destination)) {
                    File::makeDirectory($destination, 0755, true);
                }

                $existingProductUploadIds = array_filter(explode(',', $product->upload_id));
                $variationOldImageIds = [];

                // 🔴 Delete old variation images
                if ($variation->images_id) {
                    $variationOldImageIds = array_filter(explode(',', $variation->images_id));
                    $oldUploads = Upload::whereIn('id', $variationOldImageIds)->get();

                    foreach ($oldUploads as $upload) {
                        $filePath = public_path($upload->path);
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        $upload->delete();
                    }
                }

                // 🟢 Upload new images
                $newUploadIds = [];
                foreach ($uploadedImages as $image) {
                    if (!$image->isValid()) continue;

                    $fileName = time() . '_' . Str::random(8) . '.' . $image->getClientOriginalExtension();
                    $image->move($destination, $fileName);

                    $upload = Upload::create([
                        'path' => 'uploads/products/' . $fileName,
                        'url' => url('uploads/products/' . $fileName),
                        'file_name' => $fileName,
                        'extension' => $image->getClientOriginalExtension()
                    ]);

                    $newUploadIds[] = $upload->id;
                }

                // ✅ Update variation
                $variation->images_id = implode(',', $newUploadIds);
                $variation->save();

                // ✅ Update product upload_id by:
                // - Removing old variation image IDs
                // - Adding new ones
                $remainingProductUploadIds = array_diff($existingProductUploadIds, $variationOldImageIds);
                $finalUploadIds = array_unique(array_merge($remainingProductUploadIds, $newUploadIds));

                $product->upload_id = implode(',', $finalUploadIds);
                $product->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Product and variation updated successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Add Product Specs
    public function addProductSpecs(Request $request)
    {
        $validated = $request->validate([
            'uid' => 'required|numeric',
            'features' => 'required|array|min:1',
            'features.*.spec_name' => 'required|string|max:255',
            'features.*.spec_value' => 'nullable|string',
        ]);

        // ✅ Check variation exists
        $variation = ProductVariations::where('uid', $validated['uid'])->first();

        if (!$variation) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid UID'
            ], 404);
        }

        foreach ($validated['features'] as $feature) {

            // 🔁 Check if same uid + spec_name exists
            $existingSpec = ProductSpecModel::where('uid', $validated['uid'])
                ->where('spec_name', $feature['spec_name'])
                ->first();

            if ($existingSpec) {
                // ✅ UPDATE only value
                $existingSpec->update([
                    'spec_value' => $feature['spec_value']
                ]);
            } else {
                // ✅ INSERT new spec
                ProductSpecModel::create([
                    'uid' => $validated['uid'],
                    'spec_name' => $feature['spec_name'],
                    'spec_value' => $feature['spec_value'] ?? null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Product features saved successfully',
            'uid' => $validated['uid']
        ]);
    }

    // Get Product Specs
    public function getProductSpecs(Request $request)
    {
        $validated = $request->validate([
            'uid' => 'nullable|numeric',
            'spec_name' => 'nullable|string|max:255',
        ]);

        $query = ProductSpecModel::query();

        // ✅ Filter by UID (optional)
        if (!empty($validated['uid'])) {
            $query->where('uid', $validated['uid']);
        }

        // ✅ Filter by Spec Name (optional)
        if (!empty($validated['spec_name'])) {
            $query->where('spec_name', $validated['spec_name']);
        }

        $specs = $query->orderBy('spec_name')->get([
            'id',
            'uid',
            'spec_name',
            'spec_value'
        ]);

        return response()->json([
            'success' => true,
            'filters' => [
                'uid' => $validated['uid'] ?? null,
                'spec_name' => $validated['spec_name'] ?? null,
            ],
            'count' => $specs->count(),
            'data' => $specs
        ]);
    }

    // Delete Product Specs
    public function deleteProductSpecs(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|numeric',
            'uid' => 'nullable|numeric',
        ]);

        // ❌ If neither id nor uid provided
        if (empty($validated['id']) && empty($validated['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide id or uid to delete specs.'
            ], 422);
        }

        // ✅ Priority: delete by ID (single spec)
        if (!empty($validated['id'])) {
            $deleted = ProductSpecModel::where('id', $validated['id'])->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Spec not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product spec deleted successfully.',
                'deleted_by' => 'id',
                'id' => $validated['id']
            ]);
        }

        // ✅ Delete by UID (bulk specs)
        $deletedCount = ProductSpecModel::where('uid', $validated['uid'])->delete();

        if ($deletedCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No specs found for this UID.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product specs deleted successfully.',
            'deleted_by' => 'uid',
            'uid' => $validated['uid'],
            'deleted_count' => $deletedCount
        ]);
    }

}
