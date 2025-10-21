<?php

namespace App\Http\Controllers;

use Stripe\Webhook;
use Stripe\Event;
use \Stripe\PaymentIntent;
use \Stripe\PaymentMethod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use App\Models\Affiliate;
use App\Models\Transaction;
use Laravel\Cashier\Subscription;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class WebhookController extends CashierController
{

    /**
     * Handle customer subscription updated.
     */

    public function handleCustomerSubscriptionCreated(array $payload){
        $subscriptionData = $payload['data']['object'];

        $stripeCustomerId = $subscriptionData['customer'];
        
        if(!empty($stripeCustomerId)){
            $user = User::where('stripe_id', $stripeCustomerId)->first();
        }
        if(!$user){
            Log::warning("No user found for Stripe customer ID: $stripeCustomerId in handleCustomerSubscriptionCreated.");
            return;
        }            
        $startsAt = isset($subscriptionData['current_period_start']) 
            ? Carbon::createFromTimestamp($subscriptionData['current_period_start']) 
            : null;

        $endsAt = isset($subscriptionData['current_period_end']) 
                ? Carbon::createFromTimestamp($subscriptionData['current_period_end']) 
                : null;
                
        $trialEndsAt = isset($subscriptionData['trial_end']) 
                    ? Carbon::createFromTimestamp($subscriptionData['trial_end']) 
                    : null;

        // Retrieve the price and quantity details from the 'items' array
        $item = $subscriptionData['items']['data'][0];

        $user->subscriptions()->updateOrCreate(
            ['stripe_id' => $subscriptionData['id']],
            [
                // Product is used for the 'type' column
                'user_id'        => $user->id,
                'type'           => $item['price']['product'] ?? 'default', 
                'stripe_status'  => $subscriptionData['status'],
                'stripe_price'   => $item['price']['id'] ?? null,
                'quantity'       => $item['quantity'] ?? 1,
                
                // Store the start and end dates 
                'starts_at'      => $startsAt,
                'ends_at'        => $endsAt,
                'trial_ends_at'  => $trialEndsAt,
            ]
        );
    }

    public function handleCustomerSubscriptionUpdated(array $payload)
    {
        // Custom logic when subscription is updated
        \Log::info('Subscription updated', $payload);

        return parent::handleCustomerSubscriptionUpdated($payload);
    }

    /**
     * Handle customer subscription deleted.
     */
    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        // Custom logic when subscription is deleted
        \Log::info('Subscription deleted', $payload);

        return parent::handleCustomerSubscriptionDeleted($payload);
    }
    protected function handleCustomerPaymentMethodAttached(array $payload)
    {
        Log::info('💳 customer.payment_method.attached webhook received');
        
        try {
            $paymentMethod = $payload['data']['object'];
            $stripeCustomerId = $paymentMethod['customer'] ?? null;
            
            if (!$stripeCustomerId) {
                Log::warning('Payment Method attached event missing customer ID.');
                return $this->successMethod();
            }

            // 1. Find the User
            $user = User::where('stripe_id', $stripeCustomerId)->first();
            if (!$user) {
                Log::warning("No local user found for customer ID: {$stripeCustomerId}.");
                return $this->successMethod();
            }

            // 2. Extract Details
            $paymentType = $paymentMethod['type'] ?? null;
            $last4 = null;
            
            // The 'card' object exists only if type is 'card'
            if ($paymentType === 'card' && isset($paymentMethod['card']['last4'])) {
                $last4 = $paymentMethod['card']['last4'];
            }
            // You would add logic here for other types, e.g., 'us_bank_account'

            // 3. Update User Record
            $user->pm_type = $paymentType;
            $user->pm_last_four = $last4;
            $user->save();
            
            Log::info("User {$user->id} payment method updated: {$paymentType}, last4: {$last4}.");
            
        } catch (\Throwable $th) {
            Log::error('Error processing customer.payment_method.attached: ' . $th->getMessage());
        }
        
        return $this->successMethod();
    }

    protected function handleCheckoutSessionCompleted(array $payload){
        \Log::info("webhook hitt ho gaya");
        try{
        $session = $payload['data']['object'];
        if($session){
            \Log::info('Session:', [$session]);

            if ($session['payment_status'] !== 'paid') {
                \Log::warning('Payment not completed. Skipping.');
                return response()->json(['message' => 'Payment not completed'], 200);
            }

            $email = $session['customer_details']['email'] ?? null;
            $stripeCustomerId = $session['customer'];

            // update user table
            
            if ($session['mode'] === 'subscription') {

                $subscriptionId = $session['subscription'] ?? null;

                $user = User::where('stripe_id', $stripeCustomerId)->first() ?? $user = User::where('email',$email)->first();

                if ($user) {
                    // $user->pm_type = $paymentType ?? null;
                    // $user->pm_last_four = $last4;
                    $user->status = 'active';
                    $user->pending_plan_slug = null;
                    $user->save();
                    Log::info("User status updated to active for user ID {$user->id}");
                } else {
                    Log::warning("No user found for Stripe customer ID: $stripeCustomerId");
                }

            } else {
                Log::warning("No subscription ID found in checkout.session.completed for session ID: " . ($session['id'] ?? 'N/A'));
            }
            


            // send email

            $name = $session['customer_details']['name'] ?? '';
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0] ?? '';
            $lastName = $parts[1] ?? '';

            try {
                Mail::to($email)->send(new WelcomeUserMail($firstName, $lastName, $email));
                Log::info("Welcome email successfully queued for {$email}.");
            } catch (\Exception $e) {
                Log::error("Failed to send WelcomeUserMail to {$email}: " . $e->getMessage());
            }

        }else{
            Log::info("session not found");
            return $this->successMethod();

        }
        } catch (\Exception $e) {
            \Log::error('Webhook handler error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->successMethod();

            // You can return a response or just fail silently depending on your needs
        }


        return $this->successMethod();
    }

     /**
     * Handle invoice payment succeeded.
     */


    protected function handleInvoicePaid(array $payload)
    {
        Log::info('🔔 invoice.paid webhook received');
        $user = null;
        $subscription = null;
        $type = 'unknown';
        $subscriptionId = null;
        $productId = null;
        try {
            $invoice = $payload['data']['object'];
            Log::info([$invoice]);
            if($invoice){
                if (!isset($invoice['paid']) || !$invoice['paid'] || !isset($invoice['customer'])) {
                    Log::warning('Invoice not paid or missing customer ID. Ignoring.');
                    return $this->successMethod();
                }
                $stripeCustomerId = $invoice['customer'];
                $user = User::where('stripe_id',$stripeCustomerId)->first();
                if (!$user) {
                    Log::warning("No user found with stripe_id: {$stripeCustomerId}");
                    return $this->successMethod();
                }
                if(isset($invoice['subscription'])){
                    $type = 'subscription_payment';
                    $subscription = $user->subscriptions()->where('stripe_id',$invoice['subscription'])->first();
                    $subscriptionId = $subscription->id ?? null;
                } else{
                    $type = 'one_time_purchase';
                    $priceId = $invoice['lines']['data'][0]['price']['id'] ?? null;
                    $product = Products::where('price_id',$priceId)->first();
                    $productId = $product->id ?? null;
                }
                $transaction = new Transaction();
                $transaction->user_id = $user->id;
                $transaction->stripe_charge_id = $invoice['charge'] ?? null;
                $transaction->stripe_invoice_id = $invoice['id'];
                $transaction->amount = ($invoice['amount_paid'] ?? 0)/100;
                $transaction->currency = strtoupper($invoice['currency']);
                $transaction->type = $type ?? "";
                $transaction->product_id = $productId;
                $transaction->subscription_id = $subscriptionId;
                $transaction->failure_reason = null;
                $transaction->created_by = null;
                $transaction->save();

                if($type == "subscription_payment" && $subscription){
                    $referrerAffiliate = Affiliate::where('id',$user->referred_by)->first();
                    if($referrerAffiliate){
                        Stripe::setApiKey(config('services.stripe.secret'));
                        $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);
                        $firstItem = $stripeSubscription->items->data[0] ?? null;
                        $amount = null;

                        if ($firstItem && $firstItem->price && $firstItem->price->unit_amount) {
                            $amount = $firstItem->price->unit_amount / 100; // Amount for one unit
                            // To be precise, you should use the total amount from the invoice line item, 
                            // but using the unit price and calculating commission on that is acceptable.
                        }

                        if($amount !== null){
                            $commission = $amount * 0.5; // Assuming 50% commission rate
                            $referrerAffiliate->total_commission += $commission;
                            $referrerAffiliate->total_referrals += 1;
                            $referrerAffiliate->save();
                            
                            // Attempt automatic transfer
                            $referrerUser = $referrerAffiliate->user;
                            if($referrerUser && $referrerUser->stripe_connect_account_id){
                                try {
                                    $transferPayload = [
                                        'amount' => (int)round($commission * 100), // Transfer amount must be an integer (in cents)
                                        'currency' => 'usd',
                                        'destination' => $referrerUser->stripe_connect_account_id,
                                        'transfer_group' => 'commission_payout_' . $subscription->stripe_id,
                                    ];
                                    Transfer::create($transferPayload);
                                    Log::info('Stripe transfer successful for affiliate commission.', $transferPayload);
                                } catch (\Exception $e) {
                                    Log::error('Stripe transfer failed (Affiliate Payout): ' . $e->getMessage());
                                }
                            }else {
                                Log::warning('Referrer found but Connect account not ready. Skipping payout.', ['user_id' => $referrerUser->id ?? 'N/A']);
                            }
                        }
                    }
                }

                }
        } catch (\Throwable $th) {
            Log::error('Error processing invoice.paid: ' . $th->getMessage());
        }
        return $this->successMethod();
        
    }

    /**
     * Handle invoice payment failed.
     */
    public function handleInvoicePaymentFailed(array $payload)
    {
        // Custom logic when payment fails
        \Log::info('Payment failed', $payload);
        try {
            $invoice = $payload['data']['object'];
            if($invoice){
                if (!isset($invoice['paid']) || !$invoice['paid'] || !isset($invoice['customer'])) {
                    Log::warning('Invoice not paid or missing customer ID. Ignoring.');
                    return $this->successMethod();
                }
                $stripeCustomerId = $invoice['customer'];
                $subscriptionId = null;
                $productId = null;
                $user = User::where('stripe_id',$stripeCustomerId)->first();
                if (!$user) {
                    Log::warning("No user found with stripe_id: {$stripeCustomerId}");
                    return $this->successMethod();
                }
                if(!isset($invoice['subscription'])){
                    $type = 'one_time_purchase';
                    $priceId = $invoice['lines']['data'][0]['price']['id'] ?? null;
                    $product = Products::where('price_id',$priceId)->first();
                    $productId = $product->id ?? null;
                } else{
                    $type = 'subscription_payment';
                    $subscription = $user->subscriptions()->where('stripe_id',$invoice['subscription'])->first();
                    $subscriptionId = $subscription->id ?? null;

                }
                $transaction = new Transaction();
                $transaction->user_id = $user->id;
                $transaction->stripe_charge_id = $invoice['charge'] ?? null;
                $transaction->stripe_invoice_id = $invoice['id'];
                $transaction->amount = ($invoice['amount_due'] ?? $invoice['total'] ?? 0) / 100;
                $transaction->currency = strtoupper($invoice['currency']) ?? "";
                $transaction->type = $type ?? "";
                $transaction->product_id = $productId;
                $transaction->subscription_id = $subscriptionId;
                $lastError = $invoice['last_payment_error'] ?? null;
                if ($lastError) {
                    $failureMessage = $lastError['message'] . ' (' . ($lastError['code'] ?? 'unknown') . ')';
                } else {
                    $failureMessage = 'Generic failure or expired card.';
                }
                $transaction->failure_reason = $failureMessage;
                $transaction->created_by = null;
                $transaction->save();

                Log::warning("Transaction logged as FAILED for user {$user->id}. Reason: {$failureMessage}");
                return $this->successMethod();

            }
        } catch (\Throwable $th) {
            // CORRECTION 4: Log internal errors
        Log::error('Error processing invoice.payment_failed (Internal Error): ' . $th->getMessage() . ' | Payload: ' . json_encode($payload));
        }
        return $this->successMethod();
    }
}
