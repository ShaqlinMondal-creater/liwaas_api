<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminController extends Controller
{
    public function getAllUsers(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            // Optional filters
            $isLoggedIn = $request->input('is_logged_in');
            $isActive = $request->input('is_active');
            $search = $request->input('search');

            // Start query
            $query = User::select('id', 'name', 'email', 'mobile', 'role', 'address_line_1', 'is_active', 'is_logged_in');

            // Apply filters if present (enum string: 'true' or 'false')
            if (!is_null($isLoggedIn)) {
                $query->where('is_logged_in', $isLoggedIn === true ? 'true' :
                                            ($isLoggedIn === false ? 'false' : strtolower($isLoggedIn)));
            }

            if (!is_null($isActive)) {
                $query->where('is_active', $isActive === true ? 'true' :
                                            ($isActive === false ? 'false' : strtolower($isActive)));
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
                });
            }

            // Get total count after filtering
            $total = $query->count();

            // Apply offset & limit
            $users = $query->skip($offset)->take($limit)->get();

            return response()->json([
                'success' => true,
                'message' => 'Users fetched successfully.',
                'data' => $users,
                'meta' => [
                    'total' => $total,
                    'limit' => (int) $limit,
                    'offset' => (int) $offset
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
