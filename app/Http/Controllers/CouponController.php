<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Coupon;
use Carbon\Carbon;

class CouponController extends Controller
{
     // ✅ Get all active coupons
    public function getAll(Request $request)
    {
        $query = Coupon::query();

        // 🔎 Filter: key_name (search)
        if ($request->filled('key_name')) {
            $query->where('key_name', 'LIKE', '%' . $request->key_name . '%');
        }

        // 🔎 Filter: status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 🔎 Filter: start_date (from)
        if ($request->filled('start_date')) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }

        // 🔎 Filter: end_date (to)
        if ($request->filled('end_date')) {
            $query->whereDate('end_date', '<=', $request->end_date);
        }

        // 📌 Pagination: limit & offset (BODY)
        $limit  = (int) $request->input('limit', 20);   // default 20
        $offset = (int) $request->input('offset', 0);   // default 0

        $total = $query->count();

        $coupons = $query
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $coupons->makeHidden(['created_at', 'updated_at']);

        return response()->json([
            'success' => true,
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset,
            'data'    => $coupons
        ]);
    }

    // ✅ Validate & apply coupon
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $coupon = Coupon::where('key_name', $request->code)
            ->where('status', 'active')
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive coupon'
            ], 404);
        }

        $today = Carbon::today();

        if ($coupon->start_date && $today->lt(Carbon::parse($coupon->start_date))) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not started yet'
            ], 400);
        }

        if ($coupon->end_date && $today->gt(Carbon::parse($coupon->end_date))) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon expired'
            ], 400);
        }

        $coupon->makeHidden(['created_at', 'updated_at']);
        return response()->json([
            'success' => true,
            'data' => $coupon
        ]);
    }

    // ✅ Create coupon (Admin)
    public function createCoupon(Request $request)
    {
        $request->validate([
            'key_name'   => 'required|string|unique:t_coupon,key_name',
            'value'      => 'required|numeric|min:1',
            'status'     => 'required|in:active,inactive',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $coupon = Coupon::create($request->only([
            'key_name',
            'value',
            'status',
            'start_date',
            'end_date'
        ]));

        $coupon->makeHidden(['created_at', 'updated_at']);
        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully',
            'data' => $coupon
        ], 201);
    }

    // ✅ Update coupon (Admin)
    public function updateCoupon(Request $request, $id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found'
            ], 404);
        }

        $request->validate([
            'key_name'   => 'sometimes|string|unique:t_coupon,key_name,' . $coupon->id,
            'value'      => 'sometimes|numeric|min:1',
            'status'     => 'sometimes|in:active,inactive',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $coupon->update($request->only([
            'key_name',
            'value',
            'status',
            'start_date',
            'end_date'
        ]));
        $coupon->makeHidden(['created_at', 'updated_at']);
        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'data' => $coupon
        ]);
    }

    // ✅ Delete coupon (Admin)
    public function deleteCoupon($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found'
            ], 404);
        }

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully'
        ]);
    }
}
