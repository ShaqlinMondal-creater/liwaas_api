<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Extra;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ExtrasController extends Controller
{
    // 1. Add extras (default show_status = 0, upload to public/extras/)
    public function addExtras(Request $request)
    {
        $validated = $request->validate([
            'purpose_name' => 'required|string',
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'show_status' => 'nullable|boolean', // ✅ optional
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
        $destinationPath = public_path('extras');

        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        $file->move($destinationPath, $fileName);

        // ✅ Build full URL like http://192.168.1.100:8000/extras/filename.jpg
        $baseUrl = config('app.url'); // should be set correctly in .env
        $fileUrl = $baseUrl . '/extras/' . $fileName;

        $extra = Extra::create([
            'purpose_name' => $validated['purpose_name'],
            'file_name' => $fileName,
            'file_path' => $fileUrl, // ✅ store full URL
            'show_status' => $request->input('show_status', 0), // ✅ default to 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Extra image uploaded successfully.',
            'data' => $extra->makeHidden(['created_at', 'updated_at'])
        ]);
    }

    // 2. Get all extras with optional filters (status, purpose_name)
    public function getAllExtras(Request $request)
    {
        $query = Extra::query();

        // Optional filter: show_status (0 or 1)
        if ($request->has('show_status')) {
            $query->where('show_status', $request->show_status);
        }

        // Optional filter: purpose_name (partial match)
        if ($request->has('purpose_name')) {
            $query->where('purpose_name', 'like', '%' . $request->purpose_name . '%');
        }

        // ✅ Optional filter: file_name (partial match)
        if ($request->has('file_name')) {
            $query->where('file_name', 'like', '%' . $request->file_name . '%');
        }

        $extras = $query->orderByDesc('created_at')->get();

        // Optionally hide timestamps (if not handled in model)
        $extras->makeHidden(['created_at', 'updated_at']);

        return response()->json([
            'success' => true,
            'message' => 'Extras fetched successfully.',
            'data' => $extras,
            'total' => $extras->count(),
        ]);
    }

    // 3. Delete extra by ID
    public function deleteExtras($id)
    {
        $extra = Extra::find($id);

        if (!$extra) {
            return response()->json([
                'success' => false,
                'message' => 'Extra not found.'
            ], 404);
        }

        $filePath = public_path($extra->file_path);
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        $extra->delete();

        return response()->json([
            'success' => true,
            'message' => 'Extra deleted successfully.'
        ]);
    }
}
