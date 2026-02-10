<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\{
    User, Product, Category, Brand, ProductVariations,
    ProductSpecModel, Upload, Extra, Orders, OrderItems,
    Cart, Wishlist, ProductReview, Coupon,
    Payment, Shipping, Invoices, Counter
};

class AdminController extends Controller
{
    public function adminDashboard()
    {
        /* ================= USERS ================= */
        $users = [
            'total_users'       => User::count(),
            'active_users'      => User::where('is_active', 1)->count(),
            'inactive_users'    => User::where('is_active', 0)->count(),
            'verified_users'    => User::whereNotNull('email_verified_at')->count(),
            'unverified_users'  => User::whereNull('email_verified_at')->count(),
            'logged_in_users'   => User::where('is_logged_in', 'true')->count(),
        ];

        /* ================= PRODUCTS ================= */
        $products = [
            'total_products'    => Product::count(),

            'active_products'   => Product::where('product_status', 'active')->count(),
            'inactive_products' => Product::where('product_status', 'inactive')->count(),

            // 👇 YOUR LOGIC
            'simple_products'   => Product::whereNotIn(
                'aid',
                ProductVariations::select('aid')->distinct()
            )->count(),

            'variable_products' => Product::whereIn(
                'aid',
                ProductVariations::select('aid')->distinct()
            )->count(),

            'cod_available_products' =>
                Product::where('cod', 'available')->count(),

            'shipping_available_products' =>
                Product::where('shipping', 'available')->count(),

            'custom_design_products' =>
                Product::where('custom_design', 'available')->count(),

            'avg_rating' => round(Product::avg('ratings'), 1),
        ];


        /* ================= PRODUCT VARIATIONS ================= */
        $productVariations = [
            'total_variations'   => ProductVariations::count()
        ];

        /* ================= PRODUCT SPECS ================= */
        $productSpecs = [
            'total_specs' => ProductSpecModel::count(),
        ];

        /* ================= CATEGORIES ================= */
        $categories = [
            'total_categories'   => Category::count(),
            // 'active_categories'  => Category::where('status', 'active')->count(),
            // 'inactive_categories'=> Category::where('status', 'inactive')->count(),
        ];

        /* ================= BRANDS ================= */
        $brands = [
            'total_brands'   => Brand::count(),
            // 'active_brands'  => Brand::where('status', 'active')->count(),
            // 'inactive_brands'=> Brand::where('status', 'inactive')->count(),
        ];

        /* ================= UPLOADS ================= */
        $uploads = [
            'total_uploads' => Upload::count(),
        ];

        /* ================= WISHLISTS ================= */
        $wishlists = [
            'total_wishlist_items' => Wishlist::count(),
            'unique_users'         => Wishlist::distinct('user_id')->count('user_id'),
            'unique_products'      => Wishlist::distinct('aid')->count('aid'),
            'most_liked_product_likes' =>
                Wishlist::select(DB::raw('COUNT(*) as likes'))
                    ->groupBy('aid')
                    ->orderByDesc('likes')
                    ->value('likes'),
        ];

        /* ================= CART ================= */
        $cart = [
            'total_cart_items' => Cart::count(),
            // 'active_carts'     => Cart::where('is_active', 1)->count(),
            // 'abandoned_carts'  => Cart::where('is_active', 0)->count(),
        ];

        /* ================= ORDERS ================= */
        $orders = [
            'total_orders'     => Orders::count(),
            'pending_orders'   => Orders::where('delivery_status', 'pending')->count(),
            'confirmed_orders' => Orders::where('delivery_status', 'arrived')->count(),
            'shipped_orders'   => Orders::where('delivery_status', 'shipped')->count(),
            'delivered_orders' => Orders::where('delivery_status', 'delivered')->count(),
            'cancelled_orders' => Orders::where('delivery_status', 'cancel')->count(),
        ];

        /* ================= ORDER ITEMS ================= */
        $orderItems = [
            'total_order_items' => OrderItems::count(),
            'total_products_sold' => OrderItems::sum('quantity'),
        ];

        /* ================= PAYMENTS ================= */
        $payments = [
            'total_payments'      => Payment::count(),
            'paid_orders'         => Payment::where('payment_status', 'success')->count(),
            'pending_payments'    => Payment::where('payment_status', 'pending')->count(),
            'cod_orders'          => Payment::where('payment_type', 'COD')->count(),
            'pre-paid_orders'  => Payment::where('payment_type', '!=', 'COD')
                                             ->where('payment_status', 'success')->count(),
            'total_revenue'       => Payment::where('payment_status', 'success')->sum('payment_amount'),
        ];

        /* ================= SHIPPING ================= */
        $shipping = [
            'total_shipments'     => Shipping::count(),
            'pending_shipments'   => Shipping::where('shipping_status', 'pending')->count(),
            'approved_shipments'=> Shipping::where('shipping_status', 'Approved')->count(),
            'delivered_shipments' => Shipping::where('shipping_status', 'Completed')->count(),
        ];

        /* ================= INVOICES ================= */
        $invoices = [
            'total_invoices' => Invoices::count(),
        ];

        /* ================= COUPONS ================= */
        $coupons = [
            'total_coupons'   => Coupon::count(),
            'active_coupons'  => Coupon::where('status', 'active')->count(),
            'expired_coupons' => Coupon::where('status', 'inactive')->count(),
            // 'total_coupon_usage' => Coupon::sum('used_count'),
        ];

        /* ================= EXTRAS ================= */
        // $extras = [
        //     'total_extras'  => Extra::count(),
        //     'active_extras' => Extra::where('status', 'active')->count(),
        // ];

        /* ================= ADDRESSES ================= */
        $addresses = [
            'total_addresses'      => AddressModel::count(),
            'users_with_addresses' => AddressModel::distinct('user_id')->count('user_id'),
        ];

        /* ================= REVIEWS ================= */
        // $reviews = [
        //     'total_reviews'    => ProductReview::count(),
        //     'approved_reviews' => ProductReview::where('status', 'approved')->count(),
        //     'pending_reviews'  => ProductReview::where('status', 'pending')->count(),
        //     'average_rating'   => round(ProductReview::avg('rating'), 1),
        // ];

        /* ================= SECTION VIEWS ================= */
        // $sectionViews = [
        //     'total_views' => SectionView::sum('views'),
        // ];

        /* ================= COUNTERS ================= */
        // $counters = [
        //     'total_visitors'   => Counter::sum('total_visits'),
        //     'today_visitors'   => Counter::whereDate('created_at', now())->sum('total_visits'),
        //     'monthly_visitors' => Counter::whereMonth('created_at', now()->month)->sum('total_visits'),
        // ];

        return response()->json([
            'success' => true,
            'message' => 'Dashboard statistics fetched successfully',
            'data' => compact(
                'users',
                'products',
                'productVariations',
                'productSpecs',
                'categories',
                'brands',
                'uploads',
                'wishlists',
                'cart',
                'orders',
                'orderItems',
                'payments',
                'shipping',
                'invoices',
                'coupons',
                // 'extras',
                'addresses',
                // 'reviews',
                // 'sectionViews',
                // 'counters'
            )
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
