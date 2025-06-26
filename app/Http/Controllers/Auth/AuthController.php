<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // Register method
    public function register(Request $request)
    {
        try {
            // Validation rules for registration
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:6',
                'mobile' => 'required|unique:users',
                'address_line_1' => 'nullable|string',
                'address_line_2' => 'nullable|string',
                'state' => 'nullable|string',
                'city' => 'nullable|string',
                'pincode' => 'nullable|string',
                'role' => 'in:customer,admin',
                'is_active' => 'in:true,false',
                'is_logged_in' => 'in:true,false',
                'is_deleted' => 'nullable|date_format:Y-m-d H:i:s',
                'country' => 'nullable|string|max:255',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create the user with provided data
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'mobile' => $request->mobile,
                'address_line_1' => $request->address_line_1,
                'address_line_2' => $request->address_line_2,
                'state' => $request->state,
                'city' => $request->city,
                'pincode' => $request->pincode,
                'role' => $request->role ?? 'customer', // Default to 'customer'
                'is_active' => $request->is_active ?? 'false', // Default to 'false'
                'is_logged_in' => $request->is_logged_in ?? 'false', // Default to 'false'
                'is_deleted' => $request->is_deleted, // Nullable
                'country' => $request->country ?? 'INDIA', // Default to 'INDIA'
            ]);

            // If user creation is successful
            return response()->json([
                'success' => true,
                'message' => 'User registered successfully.',
                'data' => $user
            ], 201);

        } catch (\Exception $e) {
            // Catch any errors and return an error response
            return response()->json([
                'success' => false,
                'message' => 'Registration failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Login method
    // public function login(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'email' => 'required|email',
    //             'password' => 'required'
    //         ]);

    //         $user = User::where('email', $request->email)->first();

    //         if (! $user || ! Hash::check($request->password, $user->password)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Invalid credentials.'
    //             ], 401);
    //         }

    //         // Create token
    //         $token = $user->createToken('api-token')->plainTextToken;

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Login successful.',
    //             'token' => $token,
    //             'user' => $user
    //         ], 200);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Login failed.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
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

}
