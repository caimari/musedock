<?php

use Screenart\Musedock\Route;
use Screenart\Musedock\Env;

// ========== SUPERADMIN SHOP ROUTES ==========

// --- Products ---
Route::get('/musedock/shop/products', 'Shop\Controllers\Superadmin\ProductController@index')
    ->name('shop.products.index')
    ->middleware('superadmin');

Route::get('/musedock/shop/products/create', 'Shop\Controllers\Superadmin\ProductController@create')
    ->name('shop.products.create')
    ->middleware('superadmin');

Route::post('/musedock/shop/products', 'Shop\Controllers\Superadmin\ProductController@store')
    ->name('shop.products.store')
    ->middleware('superadmin');

Route::get('/musedock/shop/products/{id}/edit', 'Shop\Controllers\Superadmin\ProductController@edit')
    ->name('shop.products.edit')
    ->middleware('superadmin');

Route::put('/musedock/shop/products/{id}', 'Shop\Controllers\Superadmin\ProductController@update')
    ->name('shop.products.update')
    ->middleware('superadmin');

Route::delete('/musedock/shop/products/{id}', 'Shop\Controllers\Superadmin\ProductController@destroy')
    ->name('shop.products.destroy')
    ->middleware('superadmin');

// --- Orders ---
Route::get('/musedock/shop/orders', 'Shop\Controllers\Superadmin\OrderController@index')
    ->name('shop.orders.index')
    ->middleware('superadmin');

Route::get('/musedock/shop/orders/{id}', 'Shop\Controllers\Superadmin\OrderController@show')
    ->name('shop.orders.show')
    ->middleware('superadmin');

Route::put('/musedock/shop/orders/{id}', 'Shop\Controllers\Superadmin\OrderController@update')
    ->name('shop.orders.update')
    ->middleware('superadmin');

// --- Subscriptions ---
Route::get('/musedock/shop/subscriptions', 'Shop\Controllers\Superadmin\SubscriptionController@index')
    ->name('shop.subscriptions.index')
    ->middleware('superadmin');

Route::get('/musedock/shop/subscriptions/{id}', 'Shop\Controllers\Superadmin\SubscriptionController@show')
    ->name('shop.subscriptions.show')
    ->middleware('superadmin');

Route::post('/musedock/shop/subscriptions/{id}/cancel', 'Shop\Controllers\Superadmin\SubscriptionController@cancel')
    ->name('shop.subscriptions.cancel')
    ->middleware('superadmin');

// --- Coupons ---
Route::get('/musedock/shop/coupons', 'Shop\Controllers\Superadmin\CouponController@index')
    ->name('shop.coupons.index')
    ->middleware('superadmin');

Route::get('/musedock/shop/coupons/create', 'Shop\Controllers\Superadmin\CouponController@create')
    ->name('shop.coupons.create')
    ->middleware('superadmin');

Route::post('/musedock/shop/coupons', 'Shop\Controllers\Superadmin\CouponController@store')
    ->name('shop.coupons.store')
    ->middleware('superadmin');

Route::get('/musedock/shop/coupons/{id}/edit', 'Shop\Controllers\Superadmin\CouponController@edit')
    ->name('shop.coupons.edit')
    ->middleware('superadmin');

Route::put('/musedock/shop/coupons/{id}', 'Shop\Controllers\Superadmin\CouponController@update')
    ->name('shop.coupons.update')
    ->middleware('superadmin');

Route::delete('/musedock/shop/coupons/{id}', 'Shop\Controllers\Superadmin\CouponController@destroy')
    ->name('shop.coupons.destroy')
    ->middleware('superadmin');

// --- Settings ---
Route::get('/musedock/shop/settings', 'Shop\Controllers\Superadmin\SettingsController@index')
    ->name('shop.settings.index')
    ->middleware('superadmin');

Route::post('/musedock/shop/settings', 'Shop\Controllers\Superadmin\SettingsController@update')
    ->name('shop.settings.update')
    ->middleware('superadmin');

// ========== TENANT SHOP ROUTES ==========

$adminPath = Env::get('ADMIN_PATH_TENANT', 'admin');

// --- Tenant Admin: Products ---
Route::get("/{$adminPath}/shop/products", 'Shop\Controllers\Tenant\ShopAdminController@products')
    ->name('tenant.shop.products.index')
    ->middleware('auth');

Route::get("/{$adminPath}/shop/products/create", 'Shop\Controllers\Tenant\ShopAdminController@createProduct')
    ->name('tenant.shop.products.create')
    ->middleware('auth');

Route::post("/{$adminPath}/shop/products", 'Shop\Controllers\Tenant\ShopAdminController@storeProduct')
    ->name('tenant.shop.products.store')
    ->middleware('auth');

Route::get("/{$adminPath}/shop/products/{id}/edit", 'Shop\Controllers\Tenant\ShopAdminController@editProduct')
    ->name('tenant.shop.products.edit')
    ->middleware('auth');

Route::put("/{$adminPath}/shop/products/{id}", 'Shop\Controllers\Tenant\ShopAdminController@updateProduct')
    ->name('tenant.shop.products.update')
    ->middleware('auth');

Route::delete("/{$adminPath}/shop/products/{id}", 'Shop\Controllers\Tenant\ShopAdminController@destroyProduct')
    ->name('tenant.shop.products.destroy')
    ->middleware('auth');

// --- Tenant Admin: Orders ---
Route::get("/{$adminPath}/shop/orders", 'Shop\Controllers\Tenant\ShopAdminController@orders')
    ->name('tenant.shop.orders.index')
    ->middleware('auth');

Route::get("/{$adminPath}/shop/orders/{id}", 'Shop\Controllers\Tenant\ShopAdminController@showOrder')
    ->name('tenant.shop.orders.show')
    ->middleware('auth');

// --- Tenant Admin: Settings ---
Route::get("/{$adminPath}/shop/settings", 'Shop\Controllers\Tenant\ShopAdminController@settings')
    ->name('tenant.shop.settings.index')
    ->middleware('auth');

Route::post("/{$adminPath}/shop/settings", 'Shop\Controllers\Tenant\ShopAdminController@saveSettings')
    ->name('tenant.shop.settings.update')
    ->middleware('auth');

// --- Frontend: Catalog ---
Route::get('/shop', 'Shop\Controllers\Tenant\ShopController@catalog')
    ->name('shop.catalog');

Route::get('/shop/product/{slug}', 'Shop\Controllers\Tenant\ShopController@product')
    ->name('shop.product');

// --- Frontend: Cart ---
Route::get('/shop/cart', 'Shop\Controllers\Tenant\CartController@index')
    ->name('shop.cart');

Route::post('/shop/cart/add', 'Shop\Controllers\Tenant\CartController@add')
    ->name('shop.cart.add');

Route::post('/shop/cart/update', 'Shop\Controllers\Tenant\CartController@update')
    ->name('shop.cart.update');

Route::post('/shop/cart/remove', 'Shop\Controllers\Tenant\CartController@remove')
    ->name('shop.cart.remove');

Route::post('/shop/cart/coupon', 'Shop\Controllers\Tenant\CartController@applyCoupon')
    ->name('shop.cart.coupon');

// --- Frontend: Checkout ---
Route::get('/shop/checkout', 'Shop\Controllers\Tenant\CartController@checkout')
    ->name('shop.checkout');

Route::post('/shop/checkout', 'Shop\Controllers\Tenant\CartController@processCheckout')
    ->name('shop.checkout.process');

Route::get('/shop/checkout/success', 'Shop\Controllers\Tenant\CartController@checkoutSuccess')
    ->name('shop.checkout.success');

Route::get('/shop/checkout/cancel', 'Shop\Controllers\Tenant\CartController@checkoutCancel')
    ->name('shop.checkout.cancel');

// --- Frontend: Customer Area ---
Route::get('/shop/account', 'Shop\Controllers\Tenant\CustomerController@dashboard')
    ->name('shop.customer.dashboard')
    ->middleware('auth');

Route::get('/shop/account/orders', 'Shop\Controllers\Tenant\CustomerController@orders')
    ->name('shop.customer.orders')
    ->middleware('auth');

Route::get('/shop/account/orders/{id}', 'Shop\Controllers\Tenant\CustomerController@orderDetail')
    ->name('shop.customer.order')
    ->middleware('auth');

Route::get('/shop/account/subscriptions', 'Shop\Controllers\Tenant\CustomerController@subscriptions')
    ->name('shop.customer.subscriptions')
    ->middleware('auth');

Route::post('/shop/account/subscriptions/{id}/cancel', 'Shop\Controllers\Tenant\CustomerController@cancelSubscription')
    ->name('shop.customer.subscription.cancel')
    ->middleware('auth');

// --- Webhooks (no auth, verified by Stripe signature) ---
Route::post('/shop/webhook/stripe', 'Shop\Controllers\Tenant\WebhookController@handleStripe')
    ->name('shop.webhook.stripe');
