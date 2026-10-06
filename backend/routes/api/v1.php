<?php

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Api\V1\Admin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public storefront
|--------------------------------------------------------------------------
*/
// API root: a small index so opening /v1 in a browser shows where to go instead of a 404.
Route::get('/', fn () => response()->json([
    'success' => true,
    'message' => config('app.name').' API v1',
    'data' => [
        'docs' => url('/docs'),
        'openapi' => url('/docs/openapi.yaml'),
        'health' => url('/up'),
        'examples' => [url('/v1/homepage'), url('/v1/products'), url('/v1/categories')],
    ],
]))->name('root');

Route::get('homepage', [V1\HomepageController::class, 'index'])->name('homepage');
Route::get('settings', [V1\CmsController::class, 'settings'])->name('settings');

Route::get('categories', [V1\CategoryController::class, 'index'])->name('categories.index');
Route::get('categories/{slug}', [V1\CategoryController::class, 'show'])->name('categories.show');
Route::get('brands', [V1\BrandController::class, 'index'])->name('brands.index');
Route::get('brands/{slug}', [V1\BrandController::class, 'show'])->name('brands.show');

Route::prefix('vehicles')->name('vehicles.')->group(function () {
    Route::get('manufacturers', [V1\VehicleController::class, 'manufacturers'])->name('manufacturers');
    Route::get('models', [V1\VehicleController::class, 'models'])->name('models');
    Route::get('years', [V1\VehicleController::class, 'years'])->name('years');
    Route::get('variants', [V1\VehicleController::class, 'variants'])->name('variants');
    Route::get('variants/{variant}', [V1\VehicleController::class, 'showVariant'])->name('variants.show');
});

Route::get('products', [V1\ProductController::class, 'index'])->name('products.index');
Route::get('products/filters', [V1\ProductController::class, 'filters'])->name('products.filters');
Route::get('products/{product}/compatibility', [V1\ProductController::class, 'compatibility'])->whereNumber('product')->name('products.compatibility');
Route::get('products/{product}/check-compatibility', [V1\ProductController::class, 'checkCompatibility'])->whereNumber('product')->name('products.check');
Route::get('products/{product}/related', [V1\ProductController::class, 'related'])->whereNumber('product')->name('products.related');
Route::get('products/{product}/reviews', [V1\ReviewController::class, 'index'])->whereNumber('product')->name('products.reviews');
Route::get('products/{slug}', [V1\ProductController::class, 'show'])->name('products.show');

Route::middleware('throttle:search')->group(function () {
    Route::get('search', [V1\SearchController::class, 'search'])->name('search');
    Route::get('search/suggestions', [V1\SearchController::class, 'suggestions'])->name('search.suggestions');
    Route::get('search/popular', [V1\SearchController::class, 'popular'])->name('search.popular');
});

// Cart: guests use the X-Cart-Token header, customers their Bearer token.
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [V1\CartController::class, 'show'])->name('show');
    Route::delete('/', [V1\CartController::class, 'clear'])->name('clear');
    Route::post('items', [V1\CartController::class, 'addItem'])->name('items.store');
    Route::patch('items/{item}', [V1\CartController::class, 'updateItem'])->name('items.update');
    Route::delete('items/{item}', [V1\CartController::class, 'removeItem'])->name('items.destroy');
    Route::post('apply-coupon', [V1\CartController::class, 'applyCoupon'])->name('coupon.apply');
    Route::delete('coupon', [V1\CartController::class, 'removeCoupon'])->name('coupon.remove');
    Route::put('shipping', [V1\CartController::class, 'setShipping'])->name('shipping');
});

// CMS
Route::get('banners', [V1\CmsController::class, 'banners'])->name('banners');
Route::get('pages/{slug}', [V1\CmsController::class, 'page'])->name('pages.show');
Route::get('faqs', [V1\CmsController::class, 'faqs'])->name('faqs');
Route::get('blog', [V1\CmsController::class, 'blog'])->name('blog.index');
Route::get('blog/{slug}', [V1\CmsController::class, 'blogPost'])->name('blog.show');
// Payment gateway webhooks: no user auth; each request is authenticated by its HMAC signature.
Route::post('webhooks/razorpay', [V1\RazorpayController::class, 'webhook'])->middleware('throttle:webhooks')->name('webhooks.razorpay');

Route::middleware('throttle:forms')->group(function () {
    Route::post('contact', [V1\CmsController::class, 'contact'])->name('contact');
    Route::post('newsletter', [V1\CmsController::class, 'newsletter'])->name('newsletter');
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function () {
    Route::post('register', [V1\AuthController::class, 'register'])->name('register');
    Route::post('login', [V1\AuthController::class, 'login'])->name('login');
    Route::post('forgot-password', [V1\AuthController::class, 'forgotPassword'])->name('forgot');
    Route::post('reset-password', [V1\AuthController::class, 'resetPassword'])->name('reset');
});

/*
|--------------------------------------------------------------------------
| Authenticated customer
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('auth/logout', [V1\AuthController::class, 'logout'])->name('auth.logout');

    Route::prefix('me')->name('me.')->group(function () {
        Route::get('/', [V1\ProfileController::class, 'show'])->name('show');
        Route::patch('/', [V1\ProfileController::class, 'update'])->name('update');
        Route::put('password', [V1\ProfileController::class, 'changePassword'])->name('password');
        Route::get('dashboard', [V1\ProfileController::class, 'dashboard'])->name('dashboard');
        Route::get('notifications', [V1\ProfileController::class, 'notifications'])->name('notifications');
        Route::post('notifications/read', [V1\ProfileController::class, 'markNotificationsRead'])->name('notifications.read');

        Route::get('orders', [V1\OrderController::class, 'index'])->name('orders');
        Route::get('reviews', [V1\ReviewController::class, 'mine'])->name('reviews');
        Route::delete('reviews/{review}', [V1\ReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::get('addresses', [V1\AddressController::class, 'index'])->name('addresses.index');
        Route::post('addresses', [V1\AddressController::class, 'store'])->name('addresses.store');
        Route::patch('addresses/{address}', [V1\AddressController::class, 'update'])->name('addresses.update');
        Route::delete('addresses/{address}', [V1\AddressController::class, 'destroy'])->name('addresses.destroy');

        Route::get('vehicles', [V1\CustomerVehicleController::class, 'index'])->name('vehicles.index');
        Route::post('vehicles', [V1\CustomerVehicleController::class, 'store'])->name('vehicles.store');
        Route::patch('vehicles/{vehicle}', [V1\CustomerVehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('vehicles/{vehicle}', [V1\CustomerVehicleController::class, 'destroy'])->name('vehicles.destroy');
    });

    Route::prefix('wishlist')->name('wishlist.')->group(function () {
        Route::get('/', [V1\WishlistController::class, 'index'])->name('index');
        Route::post('items', [V1\WishlistController::class, 'store'])->name('store');
        Route::delete('items/{product}', [V1\WishlistController::class, 'destroy'])->whereNumber('product')->name('destroy');
        Route::post('items/{product}/move-to-cart', [V1\WishlistController::class, 'moveToCart'])->whereNumber('product')->name('move');
    });

    Route::get('checkout', [V1\CheckoutController::class, 'show'])->name('checkout');

    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [V1\OrderController::class, 'index'])->name('index');
        Route::post('/', [V1\OrderController::class, 'store'])->middleware('throttle:checkout')->name('store');
        Route::get('{order}', [V1\OrderController::class, 'show'])->name('show');
        Route::post('{order}/cancel', [V1\OrderController::class, 'cancel'])->name('cancel');
        Route::post('{order}/pay', [V1\OrderController::class, 'pay'])->middleware('throttle:checkout')->name('pay');
        Route::post('{order}/razorpay/verify', [V1\RazorpayController::class, 'verify'])->middleware('throttle:checkout')->name('razorpay.verify');
        Route::post('{order}/razorpay/failed', [V1\RazorpayController::class, 'failed'])->middleware('throttle:checkout')->name('razorpay.failed');
        Route::get('{order}/invoice', [V1\OrderController::class, 'invoice'])->name('invoice');
        Route::get('{order}/invoice/download', [V1\OrderController::class, 'downloadInvoice'])->name('invoice.download');
    });

    Route::post('products/{product}/reviews', [V1\ReviewController::class, 'store'])->whereNumber('product')->middleware('throttle:forms')->name('products.reviews.store');
});

/*
|--------------------------------------------------------------------------
| Admin — Laravel enforces staff role + per-endpoint permissions
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('auth/login', [V1\AuthController::class, 'adminLogin'])->middleware('throttle:auth')->name('auth.login');

    Route::middleware(['auth:sanctum', 'active', 'staff'])->group(function () {
        Route::post('auth/logout', [V1\AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [Admin\MiscController::class, 'me'])->name('auth.me');
        Route::get('lookups', [Admin\MiscController::class, 'lookups'])->name('lookups');
        Route::get('notifications', [Admin\MiscController::class, 'notifications'])->name('notifications');
        Route::post('notifications/read', [Admin\MiscController::class, 'readNotifications'])->name('notifications.read');

        Route::get('dashboard', Admin\DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

        Route::middleware('permission:reports.view')->prefix('reports')->name('reports.')->group(function () {
            Route::get('sales', [Admin\ReportController::class, 'sales'])->name('sales');
            Route::get('products', [Admin\ReportController::class, 'products'])->name('products');
            Route::get('customers', [Admin\ReportController::class, 'customers'])->name('customers');
            Route::get('inventory', [Admin\ReportController::class, 'inventory'])->name('inventory');
        });

        Route::middleware('permission:products.manage')->group(function () {
            Route::prefix('product-imports')->name('product-imports.')->group(function () {
                Route::get('template', [Admin\ProductImportController::class, 'template'])->name('template');
                Route::get('columns', [Admin\ProductImportController::class, 'columns'])->name('columns');
                Route::get('/', [Admin\ProductImportController::class, 'index'])->name('index');
                Route::post('/', [Admin\ProductImportController::class, 'store'])->middleware('throttle:uploads')->name('store');
                Route::get('{productImport}', [Admin\ProductImportController::class, 'show'])->name('show');
                Route::post('{productImport}/start', [Admin\ProductImportController::class, 'start'])->name('start');
                Route::post('{productImport}/cancel', [Admin\ProductImportController::class, 'cancel'])->name('cancel');
                Route::get('{productImport}/report', [Admin\ProductImportController::class, 'report'])->name('report');
                Route::get('{productImport}/file', [Admin\ProductImportController::class, 'file'])->name('file');
                Route::delete('{productImport}', [Admin\ProductImportController::class, 'destroy'])->name('destroy');
            });
            Route::get('products', [Admin\ProductController::class, 'index'])->name('products.index');
            Route::post('products', [Admin\ProductController::class, 'store'])->name('products.store');
            Route::get('products/{id}', [Admin\ProductController::class, 'show'])->whereNumber('id')->name('products.show');
            Route::match(['put', 'patch'], 'products/{product}', [Admin\ProductController::class, 'update'])->name('products.update');
            Route::delete('products/{product}', [Admin\ProductController::class, 'destroy'])->name('products.destroy');
            Route::post('products/{id}/restore', [Admin\ProductController::class, 'restore'])->whereNumber('id')->name('products.restore');
            Route::patch('products/{product}/toggle', [Admin\ProductController::class, 'toggle'])->name('products.toggle');
            Route::post('products/{product}/images', [Admin\ProductController::class, 'uploadImages'])->name('products.images.store');
            Route::put('products/{product}/images/reorder', [Admin\ProductController::class, 'reorderImages'])->name('products.images.reorder');
            Route::patch('products/{product}/images/{image}', [Admin\ProductController::class, 'updateImage'])->name('products.images.update');
            Route::delete('products/{product}/images/{image}', [Admin\ProductController::class, 'deleteImage'])->name('products.images.destroy');
            Route::get('products/{product}/compatibilities', [Admin\ProductController::class, 'compatibilities'])->name('products.compat.index');
            Route::put('products/{product}/compatibilities', [Admin\ProductController::class, 'syncCompatibilities'])->name('products.compat.sync');

            Route::get('attributes', [Admin\MiscController::class, 'attributes'])->name('attributes.index');
            Route::post('attributes', [Admin\MiscController::class, 'storeAttribute'])->name('attributes.store');
            Route::put('attributes/{attribute}', [Admin\MiscController::class, 'updateAttribute'])->name('attributes.update');
            Route::delete('attributes/{attribute}', [Admin\MiscController::class, 'destroyAttribute'])->name('attributes.destroy');
        });

        Route::apiResource('categories', Admin\CategoryController::class)->middleware('permission:categories.manage');
        Route::apiResource('brands', Admin\BrandController::class)->middleware('permission:brands.manage');

        Route::middleware('permission:vehicles.manage')->prefix('vehicles')->name('vehicles.')->group(function () {
            Route::get('manufacturers', [Admin\VehicleController::class, 'manufacturers'])->name('manufacturers.index');
            Route::post('manufacturers', [Admin\VehicleController::class, 'storeManufacturer'])->name('manufacturers.store');
            Route::match(['put', 'patch'], 'manufacturers/{manufacturer}', [Admin\VehicleController::class, 'updateManufacturer'])->name('manufacturers.update');
            Route::delete('manufacturers/{manufacturer}', [Admin\VehicleController::class, 'destroyManufacturer'])->name('manufacturers.destroy');
            Route::get('models', [Admin\VehicleController::class, 'models'])->name('models.index');
            Route::post('models', [Admin\VehicleController::class, 'storeModel'])->name('models.store');
            Route::match(['put', 'patch'], 'models/{model}', [Admin\VehicleController::class, 'updateModel'])->name('models.update');
            Route::delete('models/{model}', [Admin\VehicleController::class, 'destroyModel'])->name('models.destroy');
            Route::get('variants', [Admin\VehicleController::class, 'variants'])->name('variants.index');
            Route::post('variants', [Admin\VehicleController::class, 'storeVariant'])->name('variants.store');
            Route::match(['put', 'patch'], 'variants/{variant}', [Admin\VehicleController::class, 'updateVariant'])->name('variants.update');
            Route::delete('variants/{variant}', [Admin\VehicleController::class, 'destroyVariant'])->name('variants.destroy');
        });

        Route::get('orders', [Admin\OrderController::class, 'index'])->middleware('permission:orders.view,orders.manage')->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->middleware('permission:orders.view,orders.manage')->name('orders.show');
        Route::get('orders/{order}/invoice', [Admin\OrderController::class, 'invoice'])->middleware('permission:orders.view,orders.manage')->name('orders.invoice');
        Route::get('orders/{order}/invoice/download', [Admin\OrderController::class, 'downloadInvoice'])->middleware('permission:orders.view,orders.manage')->name('orders.invoice.download');
        Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->middleware('permission:orders.manage')->name('orders.status');
        Route::patch('orders/{order}', [Admin\OrderController::class, 'update'])->middleware('permission:orders.manage')->name('orders.update');

        Route::middleware('permission:customers.view')->group(function () {
            Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
            Route::get('customers/{id}', [Admin\CustomerController::class, 'show'])->whereNumber('id')->name('customers.show');
            Route::patch('customers/{id}', [Admin\CustomerController::class, 'update'])->whereNumber('id')->middleware('permission:customers.manage')->name('customers.update');
        });

        Route::middleware('permission:inventory.manage')->prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', [Admin\InventoryController::class, 'index'])->name('index');
            Route::get('transactions', [Admin\InventoryController::class, 'transactions'])->name('transactions');
            Route::get('{inventory}', [Admin\InventoryController::class, 'show'])->name('show');
            Route::patch('{inventory}', [Admin\InventoryController::class, 'update'])->name('update');
            Route::post('{inventory}/adjust', [Admin\InventoryController::class, 'adjust'])->name('adjust');
        });

        Route::apiResource('coupons', Admin\CouponController::class)->middleware('permission:coupons.manage');

        Route::middleware('permission:reviews.manage')->group(function () {
            Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
            Route::post('reviews/bulk', [Admin\ReviewController::class, 'bulk'])->name('reviews.bulk');
            Route::patch('reviews/{review}', [Admin\ReviewController::class, 'update'])->name('reviews.update');
            Route::delete('reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
        });

        Route::middleware('permission:cms.manage')->group(function () {
            Route::apiResource('banners', Admin\BannerController::class);
            Route::apiResource('pages', Admin\PageController::class)->parameters(['pages' => 'id']);
            Route::apiResource('faqs', Admin\FaqController::class)->parameters(['faqs' => 'id']);
            Route::apiResource('blog', Admin\BlogPostController::class)->parameters(['blog' => 'blog']);
            Route::apiResource('blog-categories', Admin\BlogCategoryController::class)->parameters(['blog-categories' => 'id']);
            Route::apiResource('testimonials', Admin\TestimonialController::class)->parameters(['testimonials' => 'id']);
            Route::apiResource('contact-messages', Admin\ContactMessageController::class)->parameters(['contact-messages' => 'id'])->except('store');
            Route::get('newsletter-subscribers/export', [Admin\NewsletterController::class, 'export'])->name('newsletter.export');
            Route::apiResource('newsletter-subscribers', Admin\NewsletterController::class)->parameters(['newsletter-subscribers' => 'id']);
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('settings', [Admin\SettingController::class, 'index'])->name('settings.index');
            Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
            Route::get('audit-logs', [Admin\MiscController::class, 'auditLogs'])->name('audit-logs');
        });

        Route::middleware('permission:users.manage')->group(function () {
            Route::get('staff', [Admin\StaffController::class, 'index'])->name('staff.index');
            Route::post('staff', [Admin\StaffController::class, 'store'])->name('staff.store');
            Route::match(['put', 'patch'], 'staff/{user}', [Admin\StaffController::class, 'update'])->name('staff.update');
            Route::delete('staff/{user}', [Admin\StaffController::class, 'destroy'])->name('staff.destroy');
            Route::get('roles', [Admin\StaffController::class, 'roles'])->name('roles.index');
            Route::put('roles/{role}', [Admin\StaffController::class, 'updateRole'])->name('roles.update');
        });
    });
});
