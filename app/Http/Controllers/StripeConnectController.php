<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\Account;
use Stripe\Checkout\Session;
use Stripe\AccountLink;
use App\Models\User;
use App\Models\Plans;
use App\Models\Transaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Stripe\Invoice;



class StripeConnectController extends Controller
{
    public function createAccountLink(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        $user = Auth::user();

        // If the user does not have a Stripe Connect account ID, create one
        if (!$user->stripe_connect_id) {
            $account = Account::create([
                'type' => 'express',
                'email' => $user->email,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);
            $user->stripe_connect_id = $account->id;
            $user->save();
        }

        // Create the account link
        $accountLink = AccountLink::create([
            'account' => $user->stripe_connect_id,
            'refresh_url' => route('stripe.connect.refresh'), // You need to define this route
            'return_url' => route('stripe.connect.return'),   // And this one
            'type' => 'account_onboarding',
        ]);

        return response()->json([
            'success' => true,
            'url' => $accountLink->url,
        ]);
    }

    public function handleReturn(Request $request)
    {
        // Handle the user returning from Stripe onboarding
        // You can redirect them to their dashboard or a "success" page
        return redirect('/dashboard')->with('success', 'Payout account set up successfully!');
    }

    public function handleRefresh(Request $request)
    {
        // Handle the link expiring and needing to be refreshed
        return redirect()->route('stripe.connect.create');
    }

    public function updatePaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string',
        ]);

        $user = Auth::user();

        try {
            // Add the payment method to the user (if not already added)
            $user->addPaymentMethod($request->payment_method);
            // Set as default payment method
            $user->updateDefaultPaymentMethod($request->payment_method);

            return response()->json([
                'success' => true,
                'message' => 'Payment method updated successfully.',
                'default_payment_method' => $user->defaultPaymentMethod()?->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }



// public function paymentHistory(Request $request)
// {
//     $user = Auth::user();
//     $limit = (int) $request->query('per_page', 1);
//     $startingAfter = $request->query('starting_after', null);

//     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
    
//     try {
//         $total_invoices = Transaction::where('user_id', $user->id)->count();
//         Log::info('Fetching Stripe invoices', [
//             'user_id' => $user->id ?? null,
//             'stripe_id' => $user->stripe_id ?? null,
//             'limit' => $limit,
//             'starting_after' => $startingAfter,
//         ]);

//         $params = [
//             'limit' => $limit,
//             'customer' => $user->stripe_id,
//         ];

//         if ($startingAfter !== null && $startingAfter !== "null") {
//             $params['starting_after'] = $startingAfter;
//         }

//         \Log::info("params : ",$params);

//         $invoices = Invoice::all($params);

//         $invoiceData = collect($invoices->data)->map(function ($invoice) {
//             return [
//                 'id' => $invoice->id,
//                 'date' => date('Y-m-d', $invoice->created),
//                 'total' => $invoice->total / 100, // Stripe amounts are in cents
//                 'status' => $invoice->paid ? 'Paid' : 'Unpaid',
//                 'download_url' => route('stripe.invoice.download', ['invoiceId' => $invoice->id]),
//             ];
//         });

//         Log::info('Stripe invoices fetched successfully', [
//             'user_id' => $user->id,
//             'invoice_count' => count($invoiceData),
//             'has_more' => $invoices->has_more,
//         ]);

//         return response()->json([
//             'success' => true,
//             'invoices' => $invoiceData,
//             'has_more' => $invoices->has_more, // tells frontend if there are more invoices
//             'starting_after' => end($invoices->data)->id ?? null, // pass for next page fetch
//             'total_invoices' => $total_invoices,

//         ]);
//     } catch (\Stripe\Exception\ApiErrorException $e) {
//         Log::error('Stripe API error while fetching invoices', [
//             'user_id' => $user->id ?? null,
//             'stripe_id' => $user->stripe_id ?? null,
//             'message' => $e->getMessage(),
//             'trace' => $e->getTraceAsString(),
//         ]);

//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to fetch invoices from Stripe.',
//             'error' => $e->getMessage(),
//         ], 500);
//     } catch (\Exception $e) {
//         Log::error('Unexpected error while fetching invoices', [
//             'user_id' => $user->id ?? null,
//             'message' => $e->getMessage(),
//             'trace' => $e->getTraceAsString(),
//         ]);

//         return response()->json([
//             'success' => false,
//             'message' => 'An unexpected error occurred.',
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// }

public function paymentHistory(Request $request)
{
    $user = Auth::user();
    $limit = (int) $request->query('per_page', null);

    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
    
    try {
        Log::info('Fetching Stripe invoices', [
            'user_id' => $user->id ?? null,
            'stripe_id' => $user->stripe_id ?? null,
            'limit' => $limit,
        ]);

        if($limit){
            $invoices = collect($user->invoices())->take($limit);
        }else{
            $invoices = collect($user->invoices());
        }
        
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        $invoiceData = $invoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'date' => date('Y-m-d', $invoice->created),
                'total' => $invoice->total / 100, // Stripe amounts are in cents
                'status' => $invoice->paid ? 'Paid' : 'Unpaid',
                'download_url' => route('stripe.invoice.download', ['invoiceId' => $invoice->id]),
            ];
        });

        Log::info('Stripe invoices fetched successfully', [
            'user_id' => $user->id,
            'invoice_count' => count($invoiceData),
        ]);

        return response()->json([
            'success' => true,
            'invoices' => $invoiceData,
            'total_invoices' => count($invoiceData),

        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        Log::error('Stripe API error while fetching invoices', [
            'user_id' => $user->id ?? null,
            'stripe_id' => $user->stripe_id ?? null,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch invoices from Stripe.',
            'error' => $e->getMessage(),
        ], 500);
    } catch (\Exception $e) {
        Log::error('Unexpected error while fetching invoices', [
            'user_id' => $user->id ?? null,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An unexpected error occurred.',
            'error' => $e->getMessage(),
        ], 500);
    }
}



    public function downloadInvoice(Request $request, $invoiceId)
    {
        $user = Auth::user();

        Log::info('Invoice download initiated', [
            'user_id' => $user->id ?? null,
            'invoice_id' => $invoiceId,
        ]);

        try {
            // Attempt to download the invoice
            $response = $user->downloadInvoice($invoiceId, [
                'vendor'  => config('app.name'),
                'product' => 'Subscription',
            ]);

            Log::info('Invoice download successful', [
                'user_id' => $user->id,
                'invoice_id' => $invoiceId,
            ]);

            return $response;

        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Invoice download failed', [
                'user_id' => $user->id ?? null,
                'invoice_id' => $invoiceId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download invoice.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadAllInvoices(Request $request)
    {
        $user = Auth::user();
        $invoices = $user->invoices();

        $zip = new \ZipArchive();
        $fileName = storage_path('app/invoices_'.$user->id.'.zip');

        if ($zip->open($fileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            foreach ($invoices as $invoice) {
                $pdf = $user->downloadInvoice($invoice->id, [
                    'vendor'  => config('app.name'),
                    'product' => 'Subscription',
                ]);
                $pdfContent = $pdf->getContent();
                $zip->addFromString("invoice_{$invoice->id}.pdf", $pdfContent);
            }
            $zip->close();
        }

        return response()->download($fileName)->deleteFileAfterSend(true);
    }



    public function createCheckoutSession(Request $request)
    {
        $validator = Validator::make($request->json()->all(),[
            'price_slug'=>'required|string',
            'is_yearly'=>'nullable|boolean',
            'user_data'=>'required|array',
            'user_data.email'=>'required|email',
            'user_data.first_name'=>'required|string',
            'user_data.stripe_id'=>'nullable|string',
            'user_data.id'=>'required|integer',
        ]);

        if ($validator->fails()) {
            \Log::warning('Validation failed:', $validator->errors()->toArray());

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated  =$validator->validated();
        $user = $validated['user_data'];

        Stripe::setApiKey(config('services.stripe.secret'));
        $price_slug  = $validated['price_slug'];
        $price_id = Plans::where("slug",$price_slug)->pluck('stripe_price_id')->first(); 
        \Log::info("price slug : ".$price_slug);
        \Log::info("price id : ".$price_id);
        $userModel = User::find($user['id']);
        if (!$userModel) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if (empty($userModel->stripe_id) && !empty($user['stripe_id'])) {
            // If your DB user has no stripe_id, but payload has one, save it
            $userModel->stripe_id = $user['stripe_id'];
            $userModel->save();
        }

        if (empty($userModel->stripe_id)) {
            // If still no stripe_id after above, create a new Stripe customer
            $customer = \Stripe\Customer::create([
                'email' => $userModel->email,
                'name' => $userModel->first_name,
            ]);
            $userModel->stripe_id = $customer->id;
            $userModel->save();
        }


        // $frontendUrl = config('app.frontendurl');
        $frontendUrl = 'http://localhost:7535'; 

        $checkoutSession = Session::create([
            'customer' => $userModel->stripe_id, 
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $price_id, 
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $frontendUrl . '/checkout-success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontendUrl . '/checkout-cancel',
        ]);

        return response()->json(['url' => $checkoutSession->url]);
    }

    public function checkoutSuccess(Request $request){
        $validator = Validator::make($request->json()->all(),[
            'session_id'=>'required|string',
        ]);

        if ($validator->fails()) {
            \Log::warning('Validation failed:', $validator->errors()->toArray());

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated  =$validator->validated();
        $sessionId = $validated['session_id'];
        Stripe::setApiKey(config('services.stripe.secret'));

        if(!$session){
           throw new NotFoundHTTPException;
        }
        else{
             return response()->json([
                'success' => true,
                'message' => 'session found'
            ], 200);
        }
    }

}
