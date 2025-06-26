<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    // Add Category
    public function addCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'logo' => 'nullable|string'
        ]);

        $existing = Category::whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Category already exists.',
                'data' => $existing->only(['id', 'name', 'logo'])
            ], 200);
        }

        $category = Category::create([
            'name' => $validated['name'],
            'logo' => $validated['logo'] ?? null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category added successfully.',
            'data' => $category->only(['id', 'name', 'logo'])
        ], 201);
    }

    // Get All Categories (with search, limit, offset)
    public function getAllCategories(Request $request)
    {
        $limit = (int) $request->input('limit', 10);
        $offset = (int) $request->input('offset', 0);
        $search = $request->input('search');

        $query = Category::query();

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $total = $query->count();

        $categories = $query->skip($offset)->take($limit)->get(['id', 'name', 'logo']);

        return response()->json([
            'success' => true,
            'message' => 'Categories fetched successfully.',
            'data' => $categories,
            'total_categories' => $total,
            'limit' => $limit,
            'offset' => $offset
        ], 200);
    }

    // Update Category
    public function updateCategory(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240'
        ]);

        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.'
            ], 404);
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo file if it exists
            if ($category->logo) {
                $oldPath = public_path(parse_url($category->logo, PHP_URL_PATH));
                if (File::exists($oldPath)) {
                    File::delete($oldPath);
                }
            }

            $file = $request->file('logo');
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destination = public_path('uploads/categories');

            if (!File::exists($destination)) {
                File::makeDirectory($destination, 0755, true);
            }

            $file->move($destination, $fileName);
            $logoUrl = url('uploads/categories/' . $fileName);

            $category->logo = $logoUrl;
        }

        // Update name if provided
        if (isset($validated['name'])) {
            $category->name = $validated['name'];
        }

        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'logo' => $category->logo,
            ]
        ], 200);
    }

    // Delete Category
    public function deleteCategory($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.'
            ], 404);
        }

        // Delete logo file if it exists
        if ($category->logo) {
            $logoPath = public_path(parse_url($category->logo, PHP_URL_PATH));
            if (File::exists($logoPath)) {
                File::delete($logoPath);
            }
        }

        // Delete the category
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.'
        ], 200);
    }
}
