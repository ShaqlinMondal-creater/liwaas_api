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

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::prefix('products')->group(function () {
        Route::post('get-product-byslug/{slug}', [ProductController::class, 'getProductsBySlug']); // through slug product filter 
        Route::post('/allProducts', [ProductController::class, 'getAllProducts']); // All product showing 

    });

    Route::post('/allBrands', [BrandController::class, 'getAllBrands']); //All brand showing
    Route::post('/allCategories', [CategoryController::class, 'getAllCategories']); //All category showing


Route::middleware(['auth:sanctum'])->group(function () {

    // Common Routes (Admin + Customer)
    Route::get('/common', [RoleBasedController::class, 'commonPage']);

    Route::post('/logout', [AuthController::class, 'logout']); // Logout Route (Both Admin + Customer)

    // Admin Routes
    Route::middleware(['adminOnly'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'adminDashboard']);
        Route::delete('delete/{id}', [ProductReviewController::class, 'deleteReview']); // Delete review by id
        
        Route::get('/reviews', [ProductReviewController::class, 'getAllReviewsWithFilters']); // get all reviews (have filter)
        Route::post('/carts', [CartController::class, 'getAllCartsForAdmin']); // For Carts
        Route::get('/wishlists', [WishlistController::class, 'getAllWishlists']); // For Wishlist

        // Extras File
        Route::prefix('extras')->group(function () { 
            Route::post('/add', [ExtrasController::class, 'addExtras']); 
            Route::post('/getall', [ExtrasController::class, 'getAllExtras']);  
            Route::delete('/delete/{id}', [ExtrasController::class, 'deleteExtras']); 
        });

        // For Products
        Route::prefix('shiprocket')->group(function () {
            Route::get('/orders', [ShippingController::class, 'fetchAllShiprocketOrders']);
            Route::post('/order-cancel', [ShippingController::class, 'cancelShiprocketOrder']);
            Route::get('/track', [ShippingController::class, 'trackShipment']);
            Route::get('/stats', [ShippingController::class, 'getMonthlyShippingStats']);
        });

        Route::post('/shipping-by', [ShippingController::class, 'shipBy']); // For Select Shipping
        Route::get('/shiprocket-orders', [ShippingController::class, 'getShiprocketOrders']); //For get all shipping order
        

        // Route::get('/users', [AdminController::class, 'getAllUsers']); // Get all User
        Route::post('/users', [AdminController::class, 'getAllUsers']); // now accepts body

        Route::post('/getAllAddress', [AddressController::class, 'getAllAddress']); // get all address
        Route::delete('/delete-address/{address_id}', [AddressController::class, 'adminDeleteAddress']); // delete address by admin
        
        // For Products
        Route::prefix('products')->group(function () {
            Route::post('/add_product', [ProductController::class, 'addProduct']); //Add product
            Route::delete('/variation-delete/{uid}', [ProductController::class, 'deleteVariation']); // delete variation only
            Route::delete('/product-delete/{aid}', [ProductController::class, 'deleteProduct']); // delete product with there variations
            Route::post('/product-update', [ProductController::class, 'updateProduct']);
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

            Route::delete('/delete-images', [UploadController::class, 'deleteProductImages']); // Delete Product Images
        });
        

    });

    // Customer Routes
    Route::middleware(['customerOnly'])->prefix('customer')->group(function () {
        Route::get('/profile', [UserController::class, 'customerProfile']);

        Route::prefix('address')->group(function () {
            Route::post('/create-address', [AddressController::class, 'createAddress']); // create address
            Route::post('/getAddressById', [AddressController::class, 'getAddressById']); // get all address by user id
            Route::post('/update-address', [AddressController::class, 'updateAddress']); // update address by address id
            Route::delete('/delete-address/{address_id}', [AddressController::class, 'deleteAddress']); // delete address by address id
        });

        Route::prefix('cart')->group(function () {
            Route::post('/create-cart', [CartController::class, 'createCart']);
            Route::post('/update-cart', [CartController::class, 'updateCart']);
            Route::get('/get-cart', [CartController::class, 'getUserCart']);
            Route::delete('/{id}', [CartController::class, 'deleteCart']);
        });

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
            Route::delete('/delete/{id}', [OrderController::class, 'deleteOrder']); // delete order by order id
            Route::put('/update-status/{id}', [OrderController::class, 'updateOrderStatus']); // update order status
        });
        
        Route::post('/payments/verify', [PaymentController::class, 'verify']);


    });
    

});
