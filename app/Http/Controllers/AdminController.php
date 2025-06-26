<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminController extends Controller
{
    public function getAllUsers(Request $request)
    {
        try {
            $users = User::select('id', 'name', 'email', 'mobile', 'role', 'is_active')->get();

            return response()->json([
                'success' => true,
                'message' => 'Users fetched successfully.',
                'data' => $users
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
