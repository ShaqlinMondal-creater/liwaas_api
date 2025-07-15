<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;
use App\Models\Upload;
use Illuminate\Support\Str; // for form data
use Illuminate\Support\Facades\File; // for file upload

class BrandController extends Controller
{
    // POST /brands/add
    public function addBrand(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'logo' => 'nullable|string'
        ]);

        // Case-insensitive brand check
        $existing = Brand::whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Brand already exists.',
                'data' => $existing->only(['id', 'name', 'logo'])
            ], 200);
        }

        $brand = Brand::create([
            'name' => $validated['name'],
            'logo' => $validated['logo'] ?? null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand added successfully.',
            'data' => $brand->only(['id', 'name', 'logo'])
        ], 201);
    }

    // POST /brands/allBrands
    public function getAllBrands(Request $request)
    {
        $limit = (int) $request->input('limit', 10);
        $offset = (int) $request->input('offset', 0);
        $search = $request->input('search');

        $query = Brand::query();

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $total = $query->count();

        $brands = $query->skip($offset)->take($limit)->get(['id', 'name', 'logo']);

        // Modify each brand to add logo_id and resolve the logo URL
        $resolvedBrands = $brands->map(function ($brand) {
            $logoId = $brand->logo;
            $logoUrl = null;

            if (is_numeric($logoId)) {
                $upload = \App\Models\Upload::find((int) $logoId);
                $logoUrl = $upload ? $upload->url : null;
            } else {
                $logoUrl = $logoId; // Already a URL or null
            }

            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'logo_id' => is_numeric($logoId) ? (int) $logoId : null,
                'logo' => $logoUrl,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Brands fetched successfully.',
            'data' => $resolvedBrands,
            'total_brands' => $total,
            'limit' => $limit,
            'offset' => $offset
        ], 200);
    }

    // Update Brands
    public function updateBrand(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'id' => 'required|integer|exists:brands,id',
            'name' => 'sometimes|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        $brand = Brand::find($validated['id']);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found.'
            ], 404);
        }

        // Check for duplicate brand name
        if (isset($validated['name'])) {
            $exists = Brand::whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])
                ->where('id', '!=', $validated['id'])
                ->first();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Another brand with the same name already exists.'
                ], 409);
            }

            $brand->name = $validated['name'];
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/brands');

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $fileName);
            $url = url('uploads/brands/' . $fileName);
            $path = 'uploads/brands/' . $fileName;

            // Save upload info to uploads table
            $upload = Upload::create([
                'path' => $path,
                'url' => $url,
                'file_name' => $fileName,
                'extension' => $file->getClientOriginalExtension(),
            ]);

            // Set logo to new upload ID
            $brand->logo = $upload->id;
        }

        $brand->save();

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully.',
            'data' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'logo_upload_id' => $brand->logo,
                'logo_url' => isset($upload) ? $upload->url : optional(Upload::find($brand->logo))->url,
            ]
        ], 200);
    }
    
    // Delete Brands
    public function deleteBrand($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found.'
            ], 404);
        }

        // Delete logo file from disk if it exists
        if ($brand->logo) {
            $logoPath = public_path(parse_url($brand->logo, PHP_URL_PATH));
            if (File::exists($logoPath)) {
                File::delete($logoPath);
            }
        }

        // Delete the brand from DB
        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully.'
        ], 200);
    }

}
