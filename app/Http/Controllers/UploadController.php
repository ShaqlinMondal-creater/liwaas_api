<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\ProductVariations;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Upload;

class UploadController extends Controller
{
    // Upload product images
    public function uploadProductImages(Request $request)
    {
        $request->validate([
            'aid' => 'required|string|exists:products,aid',
            'file' => 'required|array',
            'file.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240' // max 10MB per image
        ]);

        $product = Product::where('aid', $request->aid)->first();

        $uploadIds = [];
        $urls = [];

        $files = $request->file('file');

        $destination = public_path('uploads/products');
        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        foreach ($files as $file) {
            if (!$file->isValid()) continue;

            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $file->move($destination, $fileName);

            $upload = Upload::create([
                'path' => 'uploads/products/' . $fileName,
                'url' => url('uploads/products/' . $fileName),
                'file_name' => $fileName,
                'extension' => $file->getClientOriginalExtension()
            ]);

            $uploadIds[] = $upload->id;
            $urls[] = $upload->url;
        }

        // ✅ Merge existing upload IDs with new ones
        $existingIds = array_filter(explode(',', $product->upload_id ?? ''));
        $allIds = array_unique(array_merge($existingIds, $uploadIds));

        // ✅ Update product's upload_id field
        $product->upload_id = implode(',', $allIds);
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded and product updated.',
            'data' => [
                'product_id' => $product->id,
                'aid' => $product->aid,
                'name' => $product->name,
                'upload_id' => implode(',', $allIds),
                'url' => $urls
            ]
        ]);
    }

    // Upload Images for a Product Variation
    public function uploadVariationsImages(Request $request)
    {
        $request->validate([
            'aid' => 'required|string|exists:products,aid',
            'uid' => 'required|string|exists:product_variations,uid',
            'file' => 'required|array',
            'file.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240'
        ]);

        $variation = ProductVariations::where('aid', $request->aid)
            ->where('uid', $request->uid)
            ->firstOrFail();

        $product = Product::where('aid', $request->aid)->firstOrFail();
        $files = $request->file('file');
        $destination = public_path('uploads/products');

        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $variationUploadIds = [];
        $variationUrls = [];

        foreach ($files as $file) {
            if (!$file->isValid()) continue;

            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $file->move($destination, $fileName);

            $upload = Upload::create([
                'path' => 'uploads/products/' . $fileName,
                'url' => url('uploads/products/' . $fileName),
                'file_name' => $fileName,
                'extension' => $file->getClientOriginalExtension()
            ]);

            $variationUploadIds[] = $upload->id;
            $variationUrls[] = $upload->url;
        }

        // Update product_variations table
        $variation->images_id = implode(',', $variationUploadIds);
        $variation->save();

        // Merge with existing product upload_id field
        $existingIds = array_filter(explode(',', $product->upload_id ?? ''));
        $allProductIds = array_unique(array_merge($existingIds, $variationUploadIds));
        $product->upload_id = implode(',', $allProductIds);
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Variation images uploaded successfully.',
            'data' => [
                'aid' => $product->aid,
                'uid' => $variation->uid,
                'images_id' => $variation->images_id,
                'url' => $variationUrls
            ]
        ]);
    }

    // Upload brand image
    public function uploadBrandImages(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|integer|exists:brands,id',
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240' // max 10MB
        ]);

        $brand = Brand::find($request->brand_id);

        $file = $request->file('file');
        $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
        $destination = public_path('uploads/brands');

        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $file->move($destination, $fileName);
        $url = url('uploads/brands/' . $fileName);

        $brand->logo = $url;
        $brand->save();

        return response()->json([
            'success' => true,
            'message' => 'Brand logo uploaded and updated successfully.',
            'data' => [
                'brand_id' => $brand->id,
                'name' => $brand->name,
                'logo_url' => $brand->logo
            ]
        ]);
    }

    // Upload category image
    public function uploadCategoryImages(Request $request)
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240' // max 10MB
        ]);

        $category = Category::find($request->category_id);

        $file = $request->file('file');
        $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
        $destination = public_path('uploads/categories');

        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $file->move($destination, $fileName);
        $url = url('uploads/categories/' . $fileName);

        $category->logo = $url;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category image uploaded and updated successfully.',
            'data' => [
                'category_id' => $category->id,
                'name' => $category->name,
                'logo_url' => $category->logo
            ]
        ]);
    }

    // Delete Product Images
    public function deleteProductImages(Request $request)
    {
        $request->validate([
            'aid' => 'required|string|exists:products,aid',
            'ids' => 'required|string' // Expecting comma-separated ids as string
        ]);
    
        $product = Product::where('aid', $request->aid)->first();
    
        // Convert the comma-separated ids to an array
        $idsToDelete = explode(',', $request->ids);
    
        // Get the existing upload IDs from the product
        $existingIds = array_filter(explode(',', $product->upload_id ?? ''));
        $remainingIds = array_diff($existingIds, $idsToDelete); // Remove the IDs to be deleted
    
        // Loop through and delete the corresponding files and records in the uploads table
        foreach ($idsToDelete as $id) {
            $upload = Upload::find($id);
            if ($upload) {
                // Delete file from storage
                $filePath = public_path($upload->path);
                if (File::exists($filePath)) {
                    File::delete($filePath);
                }
    
                // Delete the upload record
                $upload->delete();
            }
        }
    
        // Update product's upload_id field with remaining IDs
        $product->upload_id = implode(',', $remainingIds);
        $product->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Selected image(s) deleted successfully.',
            'data' => [
                'product_id' => $product->id,
                'aid' => $product->aid,
                'name' => $product->name,
                'remaining_upload_id' => $product->upload_id
            ]
        ]);
    }
    
}
