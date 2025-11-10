<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\InvestmentCalculatorController;
use App\Http\Controllers\StripeConnectController;
use App\Http\Controllers\CancellationFeedbackController;
use App\Http\Controllers\HelpSupportController;
use Illuminate\Support\Facades\Artisan;


Route::post('register', [RegisterController::class, 'register']);
Route::post('register-subscribe', [RegisterController::class, 'registerAndSubscribe']);
Route::post('create-checkout-session', [StripeConnectController::class, 'createCheckoutSession']);
Route::post('checkout-success', [StripeConnectController::class, 'checkoutSuccess']);
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LogoutController::class, 'logout']);

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);

Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::post('/webhook/stripe', [WebhookController::class, 'handleWebhook']);


Route::group(['middleware' => ['auth:api']], function () {
    Route::post('/help-support', [HelpSupportController::class, 'store']);
    Route::get('/get-all-contact', [ContactController::class, 'index'])->name('contact.index');
    Route::get('/get-contact/{contact}', [ContactController::class, 'show'])->name('contact.show');

    Route::match(['post', 'put', 'patch', 'HEAD', 'OPTIONS', 'DELETE', 'GET'], 'user/update', [UserController::class, 'updateProfile'])->name('user.update');
    Route::match(['post', 'put', 'patch', 'HEAD', 'OPTIONS', 'DELETE', 'GET'], 'get-user', [UserController::class, 'getUser']);
    Route::match(['post', 'put', 'patch', 'HEAD', 'OPTIONS', 'DELETE', 'GET'], 'user/change-password', [UserController::class, 'changePassword']);

    Route::get('/subscription', [SubscriptionController::class, 'getSubscriptionDetails']);
    Route::get('/subscription/getSubscriptionContextDetails', [SubscriptionController::class, 'getSubscriptionContextDetails']);
    Route::post('/subscription/create', [SubscriptionController::class, 'createSubscription']);
    Route::put('/subscription/update', [SubscriptionController::class, 'updateSubscription']);
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancelSubscription']);
    Route::post('/subscription/resume', [SubscriptionController::class, 'resumeSubscription']);
    Route::post('/subscription/apply-discount', [SubscriptionController::class, 'applyDiscount']);

    // Payment methods
    Route::get('/payment-methods', [SubscriptionController::class, 'getPaymentMethods']);
    Route::post('/setup-intent', [SubscriptionController::class, 'createSetupIntent']);

    // Prices
    Route::get('/prices', [SubscriptionController::class, 'getPrices']);
    Route::post('/subscription/pause', [SubscriptionController::class, 'pauseSubscription']);
    Route::match(['post', 'put', 'patch', 'HEAD', 'OPTIONS', 'DELETE', 'GET'], 'investment-calculate', [InvestmentCalculatorController::class, 'calculate']);
    // Route::post('/stripe/webhook', [StripeController::class, 'handleWebhook']);

    Route::match(
        ['post', 'put', 'patch', 'HEAD', 'OPTIONS', 'DELETE', 'GET'],
        'investment-calculate/get-all',
        [InvestmentCalculatorController::class, 'getAllCalculations']
    );
    Route::match(
        ['post', 'put', 'patch', 'HEAD', 'OPTIONS', 'DELETE', 'GET'],
        'investment-calculate/latest',
        [InvestmentCalculatorController::class, 'getLatestCalculation']
    );


    Route::match(['post', 'put', 'patch', 'head', 'options', 'delete', 'get'], '/affiliate/link', [AffiliateController::class, 'generateLink'])->name('affiliate.generate');

    Route::get('affiliate/info', [AffiliateController::class, 'getAffiliateInfo']);

    Route::get('subscriptions/get-all-amounts', [SubscriptionController::class, 'getAllSubscriptionAmounts']);

    Route::get('/user', [UserController::class, 'getUser'])->middleware('auth:api');
    Route::get('/user/plan', [UserController::class, 'getUserPlan']);
    Route::get('/subscription/getSubscriptionTier', [SubscriptionController::class, 'getSubscriptionTier']);

    // Stripe Connect Routes
    Route::get('/stripe/connect/create', [StripeConnectController::class, 'createAccountLink'])->name('stripe.connect.create');
    Route::post('/stripe/payment-method/update', [StripeConnectController::class, 'updatePaymentMethod']);
    Route::post('/submit-competition-feedback-form', [CancellationFeedbackController::class, 'CreateFeedBack']);    
    Route::get('/stripe/invoices/download-all', [StripeConnectController::class, 'downloadAllInvoices']); 

    Route::get('/stripe/invoice/{invoiceId}/download', [StripeConnectController::class, 'downloadInvoice'])->name('stripe.invoice.download'); 
    Route::get('/stripe/payment-history', [StripeConnectController::class, 'paymentHistory']);

});
Route::match(['post', 'put', 'patch', 'head', 'options', 'delete', 'get'], '/affiliate/click/{code}', [AffiliateController::class, 'trackClick'])->name('affiliate.click');



Route::get('/stripe/connect/return', [StripeConnectController::class, 'handleReturn'])->name('stripe.connect.return');
Route::get('/stripe/connect/refresh', [StripeConnectController::class, 'handleRefresh'])->name('stripe.connect.refresh');

Route::get('blogs', [BlogController::class, 'index']);       // All blogs
Route::get('blogs/{id}', [BlogController::class, 'show']);   // Single blog

// Route::middleware(['auth:sanctum'])->group(function () {
//     Route::get('/affiliate/link', [AffiliateController::class, 'generateLink'])->name('affiliate.generate');
//     Route::get('/affiliate/click/{code}', [AffiliateController::class, 'trackClick'])->name('affiliate.click');
// });

Route::middleware(['auth:api', 'admin'])->group(function () {
    // Route::match(['post', 'put', 'patch', 'HEAD', 'OPTIONS', 'DELETE', 'GET'], 'blogs/{id?}', [BlogController::class, 'getAllOrOneOrDestroy']);
    Route::delete('blogs/{id}', [BlogController::class, 'destroy']); // Delete blog
    Route::match(['post', 'put', 'patch', 'HEAD', 'OPTIONS'], 'blogs/{id?}', [BlogController::class, 'storeOrUpdate']);
});

Route::get('/test', function () {
    return response()->json(['status' => 'API working']);
});




