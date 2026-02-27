<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\ExtrasController;
use App\Http\Controllers\SectionViewController;
use App\Http\Controllers\HelperController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\CouponController;

    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/auth/google', [AuthController::class, 'googleLogin']);

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::prefix('products')->group(function () {
        Route::post('get-product-byslug/{slug}', [ProductController::class, 'getProductsBySlug']); // through slug product filter 
        Route::post('/allProducts', [ProductController::class, 'getAllProducts']); // All product showing
        Route::post('/allProductVariations', [ProductController::class, 'getAllProductVariations']); // All product showing 
        Route::post('get-product-byAid/{aid}', [ProductController::class, 'getProductsByAid']); // through AID product filter
        Route::post('get-product-byUid/{uid}', [ProductController::class, 'getProductsByUid']); // through UID

        Route::get('/newarrivals', [SectionViewController::class, 'getNewArriaval']); // get New arrival
        Route::get('/trendings', [SectionViewController::class, 'getTrendings']); // get trending 
        Route::get('/gallery', [SectionViewController::class, 'getGallery']); //get gallary
        Route::post('/cat-products/{category_id}', [SectionViewController::class, 'getCategoryProducts']); //get category wise products

    });
    

    Route::get('/filters', [HelperController::class, 'getFilters']); // Get all Filter for products
    Route::post('/allBrands', [BrandController::class, 'getAllBrands']); //All brand showing
    Route::post('/allCategories', [CategoryController::class, 'getAllCategories']); //All category showing
    Route::post('/extras/getall', [ExtrasController::class, 'getAllExtras']);  //For Get Extras with Filter
    Route::post('sections/getsections-products', [SectionViewController::class, 'getSectionsProducts']); 

    Route::prefix('cart')->group(function () {
        Route::post('/create-cart', [CartController::class, 'createCart']);
        Route::post('/update-cart/{id}', [CartController::class, 'updateCart']);
        Route::post('/get-cart', [CartController::class, 'getUserCart']);
        Route::delete('delete-cart/{id}', [CartController::class, 'deleteCart']);
    });

    Route::post('/make_user', [AuthController::class, 'makeUser']);
    Route::post('/coupons/validate-coupon', [CouponController::class, 'validateCoupon']); // validate/apply

    // Cron Job Routes
    Route::post('/payments/checkPaymentCorn', [PaymentController::class, 'autoUpdatePendingOrders']); // Payment Verification


Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']); // Logout Route (Both Admin + Customer)

    // Admin Routes
    Route::middleware(['adminOnly'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'adminDashboard']);
        Route::post('/truncate-table', [AdminController::class, 'truncateTable']); // Truncate Table
        Route::delete('delete/{id}', [ProductReviewController::class, 'deleteReview']); // Delete review by id
        

        Route::get('/reviews', [ProductReviewController::class, 'getAllReviewsWithFilters']); // get all reviews (have filter)
        
        Route::post('/carts', [CartController::class, 'getAllCartsForAdmin']); // For Carts
        Route::delete('/cart/delete/{id}', [CartController::class, 'deleteCartByAdmin']); // For Carts

        Route::post('/wishlists', [WishlistController::class, 'getAllWishlistsForAdmin']); // For Wishlist
        Route::delete('/wishlist/delete/{id}', [WishlistController::class, 'deleteWishlistByAdmin']); // For Wishlist

        // Settings Data
            // Sections Get API DATA no Table
            Route::prefix('fetch')->group(function () { 
                Route::get('/newarrivals', [SectionViewController::class, 'getNewArriaval']); // get New arrival
                Route::get('/trendings', [SectionViewController::class, 'getTrendings']); // get trending 
                Route::get('/gallery', [SectionViewController::class, 'getGallery']); //get gallary
                Route::post('/cat-products/{category_id}', [SectionViewController::class, 'getCategoryProducts']); //get category wise products
                Route::post('/marked-section-products', [SectionViewController::class, 'markedSectionProducts']); //Marked Products
            });

            // Operations with Sections Table
            Route::prefix('sections')->group(function () { 
                Route::post('/add', [SectionViewController::class, 'addSection']); 
                Route::post('/getsections', [SectionViewController::class, 'getSections']); 
                Route::delete('/delete/{id}', [SectionViewController::class, 'deleteSections']);
                Route::put('/update/{id}', [SectionViewController::class, 'updateSection']);

            });

            // Extras File
            Route::prefix('extras')->group(function () { 
                Route::post('/add', [ExtrasController::class, 'addExtras']); //For Add Extras
                Route::delete('/delete/{id}', [ExtrasController::class, 'deleteExtras']); //For Delete Extras
                Route::put('/update-status/{id}', [ExtrasController::class, 'updateStatus']); //For update Status
            });

            // For Products
            Route::prefix('shiprocket')->group(function () {
                Route::get('/orders', [ShippingController::class, 'fetchAllShiprocketOrders']); // track shiprocket all orders
                Route::post('/order-cancel', [ShippingController::class, 'cancelShiprocketOrder']);
                Route::get('/track', [ShippingController::class, 'trackShipment']);  // track shiprocket orders
                Route::get('/stats', [ShippingController::class, 'getMonthlyShippingStats']); // track shiprocket orders stats
            });
        // End Settings Data

        Route::post('/shipping-by', [ShippingController::class, 'shipBy']); // For create and select Shipping
        Route::get('/shiprocket-orders', [ShippingController::class, 'getShiprocketOrders']); //For get all shipping order from DB
        
        Route::post('/shiprocket/create-order', [ShippingController::class, 'createShiprocketOrder']); // For create order in shiprocket
        Route::post('/shiprocket/serviceability', [ShippingController::class, 'checkServiceability']); // For check serviceability

        Route::post('/users', [AdminController::class, 'getAllUsers']); // now accepts body
        Route::post('/getAllAddress', [AddressController::class, 'getAllAddress']); // get all address
        Route::delete('/delete-address/{address_id}', [AddressController::class, 'adminDeleteAddress']); // delete address by admin
        
        // For Products
        Route::prefix('products')->group(function () {
            Route::post('/add_product', [ProductController::class, 'addProduct']); //Add product
            Route::get('/get-next-count', [ProductController::class, 'getNextProductAndVariationCodes']); //Get Latest Count
            Route::delete('/variation-delete/{uid}', [ProductController::class, 'deleteVariation']); // delete variation only
            Route::delete('/product-delete/{aid}', [ProductController::class, 'deleteProduct']); // delete product with there variations
            // Route::post('/product-update', [ProductController::class, 'updateProduct']);
            Route::post('/update-product', [ProductController::class, 'updateProductDetails']);
            Route::post('/add-specs', [ProductController::class, 'addProductSpecs']);
            Route::post('/fetch-specs', [ProductController::class, 'getProductSpecs']);
            Route::post('/delete-specs', [ProductController::class, 'deleteProductSpecs']);
        });
        
        // For Brand
        Route::prefix('brands')->group(function () {
            Route::post('/add', [BrandController::class, 'addBrand']); // add brand
            Route::post('/update', [BrandController::class, 'updateBrand']); // update brand
            Route::delete('/delete/{id}', [BrandController::class, 'deleteBrand']); // delete brand
        });

        // For Category
        Route::prefix('categories')->group(function () {
            Route::post('/add', [CategoryController::class, 'addCategory']); // add category
            Route::post('/update', [CategoryController::class, 'updateCategory']); // update catgeory
            Route::delete('/delete/{id}', [CategoryController::class, 'deleteCategory']); // delete category
        });

        // For Upload data
        Route::prefix('upload')->group(function () {
            Route::post('/product-images', [UploadController::class, 'uploadProductImages']); // Upload Product Images
            Route::post('/variation-images', [UploadController::class, 'uploadVariationsImages']); // upload product variations image
            Route::post('/brand-images', [UploadController::class, 'uploadBrandImages']); // Upload Brand Images
            Route::post('/category-images', [UploadController::class, 'uploadCategoryImages']); // Upload Category Images

            Route::post('/delete-images', [UploadController::class, 'deleteProductImages']); // Delete Product Images
            Route::post('/delete-variation-images', [UploadController::class, 'deleteVariationImages']); // Delete Product Images

            Route::post('/all-images', [UploadController::class, 'getAllUploads']); // Delete Product Images
        });
        
        // For Orders
        Route::prefix('order')->group(function () {
            Route::post('/get-order', [OrderController::class, 'getAllOrders']); //get all order
            Route::put('/update-status/{id}', [OrderController::class, 'updateOrderStatus']); // update order status
            Route::delete('/delete/{id}', [OrderController::class, 'deleteOrder']); // delete order by order id
        }); 

        // For Coupons
        Route::prefix('coupons')->group(function () {
            Route::post('/get-all', [CouponController::class, 'getAll']);                // all active
            // Admin routes
            Route::post('/create', [CouponController::class, 'createCoupon']);
            Route::post('/update/{id}', [CouponController::class, 'updateCoupon']);
            Route::delete('/delete/{id}', [CouponController::class, 'deleteCoupon']);
        });

    });

    
    // Customer Routes
    Route::middleware(['customerOnly'])->prefix('customer')->group(function () {

        Route::prefix('profile')->group(function () {
            Route::post('/update', [AuthController::class, 'updateProfile']); // Update my Profile
            // Route::post('/deactive', [AuthController::class, 'getAddressByUser']); // Deactive my Profile
            Route::post('/fetch', [AuthController::class, 'getProfile']); // Fetch my Profile
            // Route::delete('/forget-password', [AuthController::class, 'deleteAddress']); // Update my password
        });

        Route::prefix('address')->group(function () {
            Route::post('/create-address', [AddressController::class, 'createAddress']); // create address
            Route::post('/getAddressBy-user', [AddressController::class, 'getAddressByUser']); // get all address by user id
            Route::post('/update-address', [AddressController::class, 'updateAddress']); // update address by address id
            Route::delete('/delete-address/{address_id}', [AddressController::class, 'deleteAddress']); // delete address by address id
        });

        // Route::prefix('cart')->group(function () {
        //     Route::post('/create-cart', [CartController::class, 'createCart']);
        //     Route::post('/update-cart', [CartController::class, 'updateCart']);
        //     Route::get('/get-cart', [CartController::class, 'getUserCart']);
        //     Route::delete('/{id}', [CartController::class, 'deleteCart']);
        // });

        Route::prefix('wishlist')->group(function () {
            Route::post('/create', [WishlistController::class, 'addWishlist']);
            Route::get('/get', [WishlistController::class, 'getUserWishlist']);
            Route::delete('/remove/{id}', [WishlistController::class, 'removeFromWishlist']);
        });

        Route::prefix('reviews')->middleware('auth:sanctum')->group(function () {
            Route::post('/create', [ProductReviewController::class, 'addReview']); // create product
            Route::post('/update/{id}', [ProductReviewController::class, 'updateReview']); // update product
            Route::get('/product/{id}', [ProductReviewController::class, 'getReviewsByProductId']); // get all review product id wise
            Route::get('/all', [ProductReviewController::class, 'getAllReviewsWithFilters']); // get all reviews (have filter)
        });

        Route::prefix('order')->group(function () {
            Route::post('/create', [OrderController::class, 'createOrder']); //create order
            Route::post('/get-order-detail', [OrderController::class, 'getMyOrderDetail']); //get all order
            Route::post('/get-order', [OrderController::class, 'getMyOrders']); //get my order details
            Route::post('/upd-payment', [OrderController::class, 'handlePaymentCallback']); //get all order 
            
        });
        
        Route::post('/payments/verify', [PaymentController::class, 'verifyPayment']); // Payment Verification

    });
    
    
    Route::post('/payments/cancelPayment', [PaymentController::class, 'cancelPayment']); // Payment Verification
});
