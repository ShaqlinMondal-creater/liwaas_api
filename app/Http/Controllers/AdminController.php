<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\{
    User, Product, Category, Brand, ProductVariations,
    ProductSpecModel, Extra, Orders, OrderItems,
    Cart, Wishlist, ProductReview, Coupon,
    Payment, Shipping, Invoices, Counter
};

class AdminController extends Controller
{
    public function adminDashboard()
    {
        /* ============================
           USERS
        ============================ */
        $users = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'users_with_cart' => Cart::distinct('user_id')->count('user_id'),
            'users_with_wishlist' => Wishlist::distinct('user_id')->count('user_id'),
        ];

        /* ============================
           PRODUCTS & CATALOG
        ============================ */
        $products = [
            'total_products' => Product::count(),
            'active_products' => Product::where('status', 'active')->count(),
            'inactive_products' => Product::where('status', 'inactive')->count(),
            'total_categories' => Category::count(),
            'total_brands' => Brand::count(),
            'total_variations' => ProductVariations::count(),
            'total_specifications' => ProductSpecModel::count(),
            'total_extras' => Extra::count(),
        ];

        /* ============================
           ORDERS & SALES
        ============================ */
        $orders = [
            'total_orders' => Orders::count(),
            'completed_orders' => Orders::where('status', 'completed')->count(),
            'pending_orders' => Orders::where('status', 'pending')->count(),
            'cancelled_orders' => Orders::where('status', 'cancelled')->count(),
            'total_items_sold' => OrderItems::sum('quantity'),
            'total_revenue' => Orders::where('status', 'completed')->sum('grand_total'),
        ];

        /* ============================
           PAYMENTS
        ============================ */
        $payments = [
            'total_payments' => Payment::count(),
            'online_payments' => Payment::where('method', 'online')->count(),
            'cod_payments' => Payment::where('method', 'cod')->count(),
            'successful_payments' => Payment::where('status', 'success')->count(),
            'pending_payments' => Payment::where('status', 'pending')->count(),
        ];

        /* ============================
           SHIPPING
        ============================ */
        $shipping = [
            'total_shipments' => Shipping::count(),
            'shipped_orders' => Shipping::where('status', 'shipped')->count(),
            'pending_shipments' => Shipping::where('status', 'pending')->count(),
        ];

        /* ============================
           WISHLIST & REVIEWS
        ============================ */
        $wishlist = [
            'total_wishlists' => Wishlist::count(),
            'most_liked_product' => Wishlist::select('product_id', DB::raw('COUNT(*) as likes'))
                ->groupBy('product_id')
                ->orderByDesc('likes')
                ->first(),
        ];

        $reviews = [
            'total_reviews' => ProductReview::count(),
            'average_rating' => round(ProductReview::avg('rating'), 2),
        ];

        /* ============================
           COUPONS & DISCOUNTS
        ============================ */
        $coupons = [
            'total_coupons' => Coupon::count(),
            'active_coupons' => Coupon::where('status', 'active')->count(),
            'used_coupons' => Orders::whereNotNull('coupon_id')->count(),
        ];

        /* ============================
           INVOICES & COUNTERS
        ============================ */
        $system = [
            'total_invoices' => Invoices::count(),
            'order_counter' => Counter::first(),
        ];

        /* ============================
           FINAL RESPONSE
        ============================ */
        return response()->json([
            'success' => true,
            'message' => 'Admin dashboard statistics fetched successfully',
            'data' => [
                'users' => $users,
                'products' => $products,
                'orders' => $orders,
                'payments' => $payments,
                'shipping' => $shipping,
                'wishlist' => $wishlist,
                'reviews' => $reviews,
                'coupons' => $coupons,
                'system' => $system,
            ]
        ]);
    }
    
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
            $query = User::select('id', 'name', 'email', 'mobile', 'role', 'is_active', 'is_logged_in');

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

    public function truncateTable(Request $request)
    {
        $validated = $request->validate([
            'table_name' => 'required|string'
        ]);

        $input = $validated['table_name'];

        $allowedTables = [
            'addresses',
            'brands',
            'carts',
            'categories',
            'counters',
            'extras',
            'g_sheets',
            'orders',
            'order_details',
            'products',
            'product_reviews',
            'product_variations',
            'section_views',
            't_coupon',
            't_invoice',
            't_payments',
            't_shipping',
            'uploads',
            'users',
            'wishlists'
        ];

        try {
            if ($input === 'all') {
                foreach ($allowedTables as $table) {
                    DB::table($table)->truncate();
                }
            } else {
                $tables = array_map('trim', explode(',', $input));

                foreach ($tables as $table) {
                    if (!in_array($table, $allowedTables)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Table '{$table}' is not allowed to truncate."
                        ], 400);
                    }

                    DB::table($table)->truncate();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Tables truncated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error truncating tables: ' . $e->getMessage()
            ], 500);
        }
    }

}
