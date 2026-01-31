<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Cart;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Mail\CreateUserMail;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;
use App\Services\FirebaseAuthService;

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

    // Google Login
    public function googleLogin(Request $request, FirebaseAuthService $firebase)
    {
        $request->validate([
            'idToken' => 'required|string',
        ]);

        try {
            // ✅ Verify Firebase token
            $claims = $firebase->verifyIdToken($request->idToken);

            $email = $claims['email'] ?? null;
            $name  = $claims['name'] ?? 'Google User';
            $firebaseUid = $claims['sub'];

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token',
                ], 401);
            }

            $user = User::where('email', $email)->first();

            // 🆕 New Google user
            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(16)),
                    'auth_provider' => 'google',
                    'google_id' => $firebaseUid,
                    'email_verified_at' => now(),
                    'role' => 'customer',
                    'is_active' => 'true',
                    'is_logged_in' => 'true',
                ]);
            } else {
                // 🚨 Security check: Prevent different Google account hijack
                if ($user->google_id && $user->google_id !== $firebaseUid) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Google account mismatch detected.',
                    ], 403);
                }

                // 🚨 Prevent login if this email was created using password login
                if ($user->auth_provider === 'local') {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email is registered with password login.',
                    ], 403);
                }

                // ✅ Mark email verified if not already verified
                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                }

                // ✅ Update Google info safely
                $user->google_id = $firebaseUid;
                $user->auth_provider = 'google';
                $user->is_logged_in = 'true';

                $user->save();
            }


            // 🔐 Revoke old tokens
            $user->tokens()->delete();

            // ✅ Create new Sanctum token
            $token = $user->createToken('api-token')->plainTextToken;

            $userData = $user->toArray();
            unset(
                $userData['password'],
                $userData['created_at'],
                $userData['updated_at'],
                $userData['email_verified_at'],
                $userData['is_deleted'],
                $userData['is_logged_in']
            );

            return response()->json([
                'success' => true,
                'message' => 'Google login successful',
                'token' => $token,
                'user' => $userData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed',
            ], 401);
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

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found'
            ], 404);
        }

        // Generate 6 digit OTP
        $otp = rand(100000, 999999);

        // Save OTP (hashed for security)
        $user->otp = Hash::make($otp);
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        // Send Email
        Mail::to($user->email)->send(new OtpMail($otp));

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:6'
        ]);

        $user = User::where('email', $request->email)->first();

        if (
            !$user ||
            !$user->otp ||
            !Hash::check($request->otp, $user->otp) ||
            now()->greaterThan($user->otp_expires_at)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'password' => 'required|min:6|confirmed'
        ]);

        $user = User::where('email', $request->email)->first();

        if (
            !$user ||
            !$user->otp ||
            !Hash::check($request->otp, $user->otp) ||
            now()->greaterThan($user->otp_expires_at)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ], 400);
        }

        // Update password
        $user->password = $request->password; // hashed automatically (model cast)
        
        // Clear OTP after use
        $user->otp = null;
        $user->otp_expires_at = null;

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful'
        ]);
    }

    // Guest to AUth User Make
    public function makeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|unique:users,mobile',
            'guest_id' => 'required|string'
        ]);

        // ✅ Check if guest_id exists in carts table
        $guestCartExists = Cart::where('user_id', $request->guest_id)->exists();
        if (!$guestCartExists) {
            return response()->json([
                'success' => false,
                'message' => 'No cart found for the provided guest_id',
            ], 404);
        }

        // ✅ Generate random password
        $password = Str::random(8);

        // ✅ Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => Hash::make($password),
            'role' => 'customer',
            'is_active' => 'true',
            'is_logged_in' => 'true',
        ]);

        // ✅ Send mail with name, email, mobile, password
        Mail::to($user->email)->send(new CreateUserMail($user, $password));


        // ✅ Replace guest_id with new user_id in carts table
        Cart::where('user_id', $request->guest_id)
            ->update(['user_id' => $user->id]);

        $user->makeHidden(['created_at', 'updated_at']);
        // ✅ Generate token
        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User created successfully and cart updated',
            'token' => $token,
            'user' => $user,
        ], 201);
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
