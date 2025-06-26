<?php

namespace App\Http\Controllers;
use App\Models\AddressModel;  // Import the Address model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AddressController extends Controller
{
    /**
     * Store a newly created address in storage.
     */
    public function createAddress(Request $request)
    {
        try {
            $authUser = $request->user(); // Get the logged-in user

            // Validation rules (remove 'registered_user' from user input)
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'address_type' => 'nullable|in:secondary,primary',
                'mobile' => 'required|string|max:15',
                'state' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'country' => 'nullable|string|max:255',
                'pincode' => 'required|string|max:20',
                'address_line_1' => 'required|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Default country if not set
            $country = $request->country ?? 'INDIA';

            // Create the address using the authenticated user ID
            $address = AddressModel::create([
                'registered_user' => $authUser->id,
                'name' => $request->name,
                'email' => $request->email,
                'address_type' => $request->address_type ?? 'secondary',
                'mobile' => $request->mobile,
                'state' => $request->state,
                'city' => $request->city,
                'country' => $country,
                'pincode' => $request->pincode,
                'address_line_1' => $request->address_line_1,
                'address_line_2' => $request->address_line_2,
            ]);

            $addressArray = $address->toArray();
            unset($addressArray['created_at'], $addressArray['updated_at']);

            return response()->json([
                'success' => true,
                'message' => 'Address created successfully.',
                'data' => $addressArray
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Address creation failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get All address for admin, have filter by user name and register user
    public function getAllAddress(Request $request)
    {
        try {
            $query = AddressModel::query();

            // Filters from request body
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            if ($request->filled('registered_user')) {
                $query->where('registered_user', $request->registered_user);
            }

            if ($request->filled('state')) {
                $query->where('state', 'like', '%' . $request->state . '%');
            }

            if ($request->filled('pincode')) {
                $query->where('pincode', $request->pincode);
            }

            if ($request->filled('city')) {
                $query->where('city', 'like', '%' . $request->city . '%');
            }

            // Pagination
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            $total = $query->count();
            $addresses = $query->offset($offset)->limit($limit)->get();

            return response()->json([
                'success' => true,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'data' => $addresses
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch addresses.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get Address by User id as (registered user)
    public function getAddressById(Request $request)
    {
        try {
            $userId = $request->user()->id; // Get authenticated user's ID

            // Fetch addresses only for the authenticated user
            $addresses = AddressModel::where('registered_user', $userId)->get();

            // Remove created_at and updated_at if needed
            $cleanedAddresses = $addresses->map(function ($address) {
                $data = $address->toArray();
                unset($data['created_at'], $data['updated_at']);
                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Addresses fetched successfully.',
                'data' => $cleanedAddresses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch address.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // update address by address id with validet user
    public function updateAddress(Request $request)
    {
        try {
            $request->validate([
                'address_id' => 'required|exists:addresses,id',
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'address_type' => 'nullable|in:primary,secondary',
                'mobile' => 'required|string|max:15',
                'state' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'pincode' => 'required|string|max:20',
                'address_line_1' => 'required|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
            ]);

            $userId = $request->user()->id;

            // Find address belonging to the current user
            $address = AddressModel::where('id', $request->address_id)
                ->where('registered_user', $userId)
                ->first();

            if (! $address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found or not authorized.'
                ], 404);
            }

            // Update the address
            $address->update([
                'name' => $request->name,
                'email' => $request->email,
                'address_type' => $request->address_type ?? 'primary',
                'mobile' => $request->mobile,
                'state' => $request->state,
                'city' => $request->city,
                'country' => $request->country,
                'pincode' => $request->pincode,
                'address_line_1' => $request->address_line_1,
                'address_line_2' => $request->address_line_2,
            ]);

            // Return cleaned data
            $updatedData = $address->toArray();
            unset($updatedData['created_at'], $updatedData['updated_at']);

            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully.',
                'data' => $updatedData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update address.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // user delete address by address id
    public function deleteAddress($address_id, Request $request)
    {
        try {
            $userId = $request->user()->id;

            $address = AddressModel::where('id', $address_id)
                ->where('registered_user', $userId)
                ->first();

            if (! $address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found or not authorized.'
                ], 404);
            }

            $address->delete();

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete address.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Admin delete address by address id
    public function adminDeleteAddress($address_id)
    {
        try {
            $address = AddressModel::find($address_id);

            if (! $address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found.'
                ], 404);
            }

            $address->delete();

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully by admin.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete address.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
