<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Upload;

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

        // Modify each category to add logo_id and resolve the logo URL
        $resolvedCategories = $categories->map(function ($category) {
            $logoId = $category->logo;
            $logoUrl = null;

            if (is_numeric($logoId)) {
                $upload = \App\Models\Upload::find((int) $logoId);
                $logoUrl = $upload ? $upload->url : null;
            } else {
                $logoUrl = $logoId; // already a URL or null
            }

            return [
                'id' => $category->id,
                'name' => $category->name,
                'logo_id' => is_numeric($logoId) ? (int) $logoId : null,
                'logo' => $logoUrl,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Categories fetched successfully.',
            'data' => $resolvedCategories,
            'total_categories' => $total,
            'limit' => $limit,
            'offset' => $offset
        ], 200);
    }

    // Update Category
    public function updateCategory(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:categories,id',
            'name' => 'sometimes|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240'
        ]);

        $category = Category::find($validated['id']);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.'
            ], 404);
        }

        // Update name
        if (isset($validated['name'])) {
            $category->name = $validated['name'];
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {

            // Optionally delete old file (if you want to)
            if ($category->logo) {
                $oldUpload = Upload::find($category->logo);
                if ($oldUpload) {
                    $oldPath = public_path($oldUpload->path);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                    $oldUpload->delete(); // remove from uploads table
                }
            }

            $file = $request->file('logo');
            $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/categories');

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $fileName);
            $url = url('uploads/categories/' . $fileName);
            $path = 'uploads/categories/' . $fileName;

            // Save to uploads table
            $upload = Upload::create([
                'path' => $path,
                'url' => $url,
                'file_name' => $fileName,
                'extension' => $file->getClientOriginalExtension(),
            ]);

            // Save upload ID in logo column
            $category->logo = $upload->id;
        }

        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'logo_upload_id' => $category->logo,
                'logo_url' => optional(Upload::find($category->logo))->url,
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
