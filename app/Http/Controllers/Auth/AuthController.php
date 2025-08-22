<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // Register method
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|unique:users,mobile',
            'password' => 'required|string|min:6',
        ]);

        $user = new User();
        $user->name = $validatedData['name'];
        $user->email = $validatedData['email'];
        $user->password = Hash::make($validatedData['password']);
        $user->mobile = $validatedData['mobile'];
        $user->role = 'customer';
        $user->is_active = 'true';
        $user->is_logged_in = 'true'; // Automatically log in after registration
        $user->save();

        // Create token
        $token = $user->createToken('api-token')->plainTextToken;

        // Convert to array and remove unwanted fields
        $userData = $user->toArray();
        unset(
            $userData['created_at'],
            $userData['updated_at'],
            $userData['email_verified_at'],
            $userData['is_deleted'],
            $userData['is_logged_in']
        );

        return response()->json([
            'success' => true,
            'message' => 'User registered and logged in successfully.',
            'token' => $token,
            'user' => $userData,
        ], 201);
    }

    // Login method
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.'
                ], 401);
            }

            // Update is_logged_in to 'true'
            $user->is_logged_in = 'true';
            $user->save();

            // Create token
            $token = $user->createToken('api-token')->plainTextToken;

            // Convert to array and remove unwanted fields
            $userArray = $user->toArray();
            unset(
                $userArray['created_at'],
                $userArray['updated_at'],
                $userArray['email_verified_at'],
                $userArray['is_deleted'],
                $userArray['is_logged_in']
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'token' => $token,
                'user' => $userArray
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Logout method
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            // Set is_logged_in to 'false'
            $user->is_logged_in = 'false';
            $user->save();

            // Delete the current access token
            $user->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout successful.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong during logout.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Fetch Profile
    public function getProfile(Request $request)
    {
        $user = Auth::user(); // Sanctum automatically resolves user from Bearer token

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid or missing token.',
            ], 401);
        }

        // Convert to array and remove unwanted fields
        $userData = $user->toArray();
        unset(
            $userData['created_at'],
            $userData['updated_at'],
            $userData['email_verified_at'],
            $userData['is_deleted'],
            $userData['is_logged_in']
        );

        return response()->json([
            'success' => true,
            'message' => 'User profile fetched successfully.',
            'user' => $userData,
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // ✅ Validate input
        $request->validate([
            'name'         => 'nullable|string|max:255',
            'email'        => 'nullable|email|unique:users,email,' . $user->id,
            'mobile'       => 'nullable|string|max:15',
            'old_password' => 'nullable|string',
            'new_password' => 'nullable|string|min:6',
        ]);

        // ✅ Update profile details
        if ($request->filled('name')) {
            $user->name = $request->name;
        }
        if ($request->filled('email')) {
            $user->email = $request->email;
        }
        if ($request->filled('mobile')) {
            $user->mobile = $request->mobile;
        }

        // ✅ Handle password update (only if both old and new are provided)
        if ($request->filled('old_password') && $request->filled('new_password')) {
            if (!\Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Old password is incorrect'
                ], 400);
            }

            $user->password = bcrypt($request->new_password);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data'    => $user
        ], 200);
    }


}
