<?php

namespace App\Http\Controllers;

use App\Support\ShippingSettings;
use Illuminate\Http\Request;

class ShippingSettingsController extends Controller
{
    public function show()
    {
        $data = ShippingSettings::all();

        return response()->json([
            'success' => true,
            'message' => 'Shipping settings fetched successfully.',
            'data' => $data,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'min_order_price' => 'required|numeric|min:0|max:1000000',
            'shipping_charge' => 'required|numeric|min:0|max:100000',
            'enabled' => 'sometimes|boolean',
        ]);

        $data = ShippingSettings::save([
            'min_order_price' => $validated['min_order_price'],
            'shipping_charge' => $validated['shipping_charge'],
            'enabled' => $validated['enabled'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipping settings updated successfully.',
            'data' => $data,
        ]);
    }
}
