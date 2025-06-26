<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;
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

    // POST /brands/allBrands?limit=10&offset=0
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

        return response()->json([
            'success' => true,
            'message' => 'Brands fetched successfully.',
            'data' => $brands,
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
            'id' => 'required|integer|exists:brands,id', // Ensure the brand ID exists
            'name' => 'sometimes|string', // Name is optional now
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // Logo is optional
        ]);
    
        // Find the brand
        $brand = Brand::find($validated['id']);
    
        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found.'
            ], 404);
        }
    
        // Check for duplicate brand name (case-insensitive) only if 'name' is provided
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
        }
    
        // Handle logo upload only if a new logo is provided
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destination = public_path('uploads/brands');
    
            // Create directory if it doesn't exist
            if (!File::exists($destination)) {
                File::makeDirectory($destination, 0755, true);
            }
    
            // Move the uploaded logo
            $file->move($destination, $fileName);
            $logoUrl = url('uploads/brands/' . $fileName);
    
            // Update the logo URL
            $brand->logo = $logoUrl;
        } else {
            // If no logo is provided, retain the existing one
            $logoUrl = $brand->logo;
        }
    
        // Update name only if provided
        if (isset($validated['name'])) {
            $brand->name = $validated['name'];
        }
    
        // Save updated brand data
        $brand->save();
    
        // Return the response with updated brand data
        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully.',
            'data' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'logo' => $brand->logo,
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
