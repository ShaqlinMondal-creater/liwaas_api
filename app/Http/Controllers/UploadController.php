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
use Illuminate\Support\Facades\Storage;

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

        foreach ($files as $file) {
            if (!$file->isValid()) continue;

            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $fileName, 'public');

            $upload = Upload::create([
                'path' => $path,                // products/filename.jpg
                'url'  => Storage::url($path),  // /storage/products/filename.jpg
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

        /*
        |--------------------------------------------------------------------------
        | ✅ ALSO UPDATE PRODUCT VARIATIONS IMAGES
        |--------------------------------------------------------------------------
        */
        $variations = ProductVariations::where('aid', $product->aid)->get();

        foreach ($variations as $variation) {

            // Existing variation image IDs
            $existingVarIds = array_filter(
                explode(',', $variation->images_id ?? '')
            );
            // Merge + unique
            $mergedVarIds = array_unique(
                array_merge($existingVarIds, $uploadIds)
            );
            // Save back
            $variation->images_id = implode(',', $mergedVarIds);
            $variation->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded and product updated.',
            'data' => [
                'product_id' => $product->id,
                'aid' => $product->aid,
                'name' => $product->name,
                'upload_id' => implode(',', $allIds),
                'url' => $urls,
                'variations_updated' => $variations->count()
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
                Storage::disk('public')->delete($upload->path);
    
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

        $variationUploadIds = [];
        $variationUrls = [];

        foreach ($files as $file) {
            if (!$file->isValid()) continue;

            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();

            // ✅ Store in storage/app/public/products
            $path = $file->storeAs('products', $fileName, 'public');

            $upload = Upload::create([
                'path' => $path,               // products/filename.jpg
                'url'  => Storage::url($path), // /storage/products/filename.jpg
                'file_name' => $fileName,
                'extension' => $file->getClientOriginalExtension()
            ]);

            $variationUploadIds[] = $upload->id;
            $variationUrls[] = $upload->url;
        }

        // Update variation images
        $variation->images_id = implode(',', $variationUploadIds);
        $variation->save();

        // Merge with product upload_id
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
        $path = $file->storeAs('brands', $fileName, 'public');

        // Prepare upload record
        $relativePath = $path;            // brands/filename.jpg
        $url = Storage::url($path);       // /storage/brands/filename.jpg

        $upload = Upload::create([
            'path' => $relativePath,
            'url' => $url,
            'file_name' => $fileName,
            'extension' => $file->getClientOriginalExtension(),
        ]);

        // Save upload ID to brand
        $brand->logo = $upload->id;
        $brand->save();

        return response()->json([
            'success' => true,
            'message' => 'Brand logo uploaded and updated successfully.',
            'data' => [
                'brand_id' => $brand->id,
                'name' => $brand->name,
                'upload_id' => $upload->id,
                'logo_url' => $upload->url
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
        $path = $file->storeAs('categories', $fileName, 'public');

        $relativePath = $path;
        $url = Storage::url($path);

        // Save in uploads table
        $upload = Upload::create([
            'path' => $relativePath,
            'url' => $url,
            'file_name' => $fileName,
            'extension' => $file->getClientOriginalExtension(),
        ]);

        // Save upload ID to category
        $category->logo = $upload->id;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category image uploaded and updated successfully.',
            'data' => [
                'category_id' => $category->id,
                'name' => $category->name,
                'upload_id' => $upload->id,
                'logo_url' => $upload->url
            ]
        ]);
    }

    // Delete Product Variation Images
    // public function deleteProductImages(Request $request)
    // {
    //     $request->validate([
    //         'aid' => 'required|string|exists:products,aid',
    //         'ids' => 'required|string' // Expecting comma-separated ids as string
    //     ]);
    
    //     $product = Product::where('aid', $request->aid)->first();
    
    //     // Convert the comma-separated ids to an array
    //     $idsToDelete = explode(',', $request->ids);
    
    //     // Get the existing upload IDs from the product
    //     $existingIds = array_filter(explode(',', $product->upload_id ?? ''));
    //     $remainingIds = array_diff($existingIds, $idsToDelete); // Remove the IDs to be deleted
    
    //     // Loop through and delete the corresponding files and records in the uploads table
    //     foreach ($idsToDelete as $id) {
    //         $upload = Upload::find($id);
    //         if ($upload) {
    //             // Delete file from storage
    //             Storage::disk('public')->delete($upload->path);
    
    //             // Delete the upload record
    //             $upload->delete();
    //         }
    //     }
    
    //     // Update product's upload_id field with remaining IDs
    //     $product->upload_id = implode(',', $remainingIds);
    //     $product->save();
    
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Selected image(s) deleted successfully.',
    //         'data' => [
    //             'product_id' => $product->id,
    //             'aid' => $product->aid,
    //             'name' => $product->name,
    //             'remaining_upload_id' => $product->upload_id
    //         ]
    //     ]);
    // }
    
}
