<?php

namespace App\Http\Controllers;

use Stripe\Webhook;
use Stripe\Event;
use \Stripe\PaymentIntent;
use \Stripe\PaymentMethod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeUserMail;
use App\Mail\SubscriptionCancelledMail;
use App\Models\User;
use App\Models\Affiliate;
use App\Models\Transaction;
use Laravel\Cashier\Subscription;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Carbon\Carbon;
use App\Models\SubscriptionHistory;


class WebhookController extends CashierController
{

    /**
     * Handle customer subscription updated.
     */

    public function handleCustomerSubscriptionCreated(array $payload){
        \Log::info("subscription created webhook recieved");
        $subscriptionData = $payload['data']['object'];
        // $data = '{"id":"sub_1SP0E5RzsDq04jEj3lYfx93C","object":"subscription","application":null,"application_fee_percent":null,"automatic_tax":{"disabled_reason":null,"enabled":false,"liability":null},"billing_cycle_anchor":1762085861,"billing_cycle_anchor_config":null,"billing_mode":{"flexible":null,"type":"classic"},"billing_thresholds":null,"cancel_at":null,"cancel_at_period_end":false,"canceled_at":null,"cancellation_details":{"comment":null,"feedback":null,"reason":null},"collection_method":"charge_automatically","created":1762085861,"currency":"aud","customer":"cus_TLhcVT9Jj9Qrtr","days_until_due":null,"default_payment_method":null,"default_source":null,"default_tax_rates":[],"description":null,"discounts":[],"ended_at":null,"invoice_settings":{"account_tax_ids":null,"issuer":{"type":"self"}},"items":{"object":"list","data":[{"id":"si_TLhd1qk6ZvqcKM","object":"subscription_item","billing_thresholds":null,"created":1762085862,"current_period_end":1764677861,"current_period_start":1762085861,"discounts":[],"metadata":[],"plan":{"id":"price_1SDMFKRzsDq04jEjkQZpu3kG","object":"plan","active":true,"amount":4900,"amount_decimal":"4900","billing_scheme":"per_unit","created":1759310570,"currency":"aud","interval":"month","interval_count":1,"livemode":false,"metadata":[],"meter":null,"nickname":null,"product":"prod_T9fajqot3iVMst","tiers_mode":null,"transform_usage":null,"trial_period_days":null,"usage_type":"licensed"},"price":{"id":"price_1SDMFKRzsDq04jEjkQZpu3kG","object":"price","active":true,"billing_scheme":"per_unit","created":1759310570,"currency":"aud","custom_unit_amount":null,"livemode":false,"lookup_key":null,"metadata":[],"nickname":null,"product":"prod_T9fajqot3iVMst","recurring":{"interval":"month","interval_count":1,"meter":null,"trial_period_days":null,"usage_type":"licensed"},"tax_behavior":"unspecified","tiers_mode":null,"transform_quantity":null,"type":"recurring","unit_amount":4900,"unit_amount_decimal":"4900"},"quantity":1,"subscription":"sub_1SP0E5RzsDq04jEj3lYfx93C","tax_rates":[]}],"has_more":false,"total_count":1,"url":"/v1/subscription_items?subscription=sub_1SP0E5RzsDq04jEj3lYfx93C"},"latest_invoice":"in_1SP0E5RzsDq04jEjCkMcqSsb","livemode":false,"metadata":[],"next_pending_invoice_item_invoice":null,"on_behalf_of":null,"pause_collection":null,"payment_settings":{"payment_method_options":{"acss_debit":null,"bancontact":null,"card":{"network":null,"request_three_d_secure":"automatic"},"customer_balance":null,"konbini":null,"sepa_debit":null,"us_bank_account":null},"payment_method_types":["card"],"save_default_payment_method":"off"},"pending_invoice_item_interval":null,"pending_setup_intent":null,"pending_update":null,"plan":{"id":"price_1SDMFKRzsDq04jEjkQZpu3kG","object":"plan","active":true,"amount":4900,"amount_decimal":"4900","billing_scheme":"per_unit","created":1759310570,"currency":"aud","interval":"month","interval_count":1,"livemode":false,"metadata":[],"meter":null,"nickname":null,"product":"prod_T9fajqot3iVMst","tiers_mode":null,"transform_usage":null,"trial_period_days":null,"usage_type":"licensed"},"quantity":1,"schedule":null,"start_date":1762085861,"status":"incomplete","test_clock":null,"transfer_data":null,"trial_end":null,"trial_settings":{"end_behavior":{"missing_payment_method":"create_invoice"}},"trial_start":null}';
        // $subscriptionData = json_decode($data,true);
        \Log::info("subscription created payload : ",[$subscriptionData]);
        $stripeCustomerId = $subscriptionData['customer'];
        
        if(!empty($stripeCustomerId)){
            $user = User::where('stripe_id', $stripeCustomerId)->first();
        }
        if(!$user){
            Log::warning("No user found for Stripe customer ID: $stripeCustomerId in handleCustomerSubscriptionCreated.");
            return;
        }            

        // Retrieve the price and quantity details from the 'items' array
        $item = $subscriptionData['items']['data'][0];
        \Log::info("subscription created item : ",[$item]);

        $startsAt = isset($item['current_period_start']) 
            ? Carbon::createFromTimestamp($item['current_period_start']) 
            : null;

        $endsAt = isset($item['current_period_end']) 
            ? Carbon::createFromTimestamp($item['current_period_end']) 
            : null;

        $trialEndsAt = isset($subscriptionData['trial_end']) 
            ? Carbon::createFromTimestamp($subscriptionData['trial_end']) 
            : null;

        \Log::info("subscription created start at : ",[$startsAt]);
        \Log::info("subscription created end at : ",[$item['current_period_end']]);
        \Log::info("subscription created endAt : ",[$endsAt]);
        \Log::info("subscription created trail end at : ",[$trialEndsAt]);
        $priceId = $subscriptionData['price']['id'] ?? null;

        $user->subscriptions()->updateOrCreate(
            ['stripe_id' => $subscriptionData['id']],
            [
                'user_id'        => $user->id,
                'type'           => 'default', 
                'stripe_status'  => $subscriptionData['status'],
                'stripe_price'   => $priceId,
                'quantity'       => $item['quantity'] ?? 1,
                
                // Store the start and end dates 
                'starts_at'      => $startsAt,
                'ends_at'        => $endsAt,
                'trial_ends_at'  => $trialEndsAt,
            ]
        );
        $stripeItems = $subscriptionData['items']['data'] ?? [];
        \Log::info("subscription created items : ",[$stripeItems]);

        $subscription = $user->subscription('default');
        foreach ($stripeItems as $stripeItem) {
            $productId = $stripeItem['price']['product'] ?? null;
            $subscription->items()->updateOrCreate(
                ['stripe_id' => $stripeItem['id']], // Match by Stripe Item ID
                [
                    'subscription_id' => $subscription->id,
                    'stripe_product' => $productId,
                    'stripe_price' => $stripeItem['price']['id'] ?? null, // The Price ID (e.g., price_1SDM...)
                    'quantity'     => $stripeItem['quantity'] ?? 1,
                ]
            );
        }
    }

    public function handleCustomerSubscriptionUpdated(array $payload)
    {
        \Log::info('Subscription updated webhook received');
        
        $subscriptionData = $payload['data']['object'];
        // $data = '{"id":"sub_1SP0tyRzsDq04jEjW9gWUxSe","object":"subscription","application":null,"application_fee_percent":null,"automatic_tax":{"disabled_reason":null,"enabled":false,"liability":null},"billing_cycle_anchor":1762088458,"billing_cycle_anchor_config":null,"billing_mode":{"flexible":null,"type":"classic"},"billing_thresholds":null,"cancel_at":null,"cancel_at_period_end":false,"canceled_at":null,"cancellation_details":{"comment":null,"feedback":null,"reason":null},"collection_method":"charge_automatically","created":1762088458,"currency":"aud","customer":"cus_TLiKQ7ziLl2bk5","days_until_due":null,"default_payment_method":"pm_1SP0tyRzsDq04jEjlZmhgdBu","default_source":null,"default_tax_rates":[],"description":null,"discounts":[],"ended_at":null,"invoice_settings":{"account_tax_ids":null,"issuer":{"type":"self"}},"items":{"object":"list","data":[{"id":"si_TLiKzE1O3zlE2e","object":"subscription_item","billing_thresholds":null,"created":1762088459,"current_period_end":1764680458,"current_period_start":1762088458,"discounts":[],"metadata":[],"plan":{"id":"price_1SDMFKRzsDq04jEjkQZpu3kG","object":"plan","active":true,"amount":4900,"amount_decimal":"4900","billing_scheme":"per_unit","created":1759310570,"currency":"aud","interval":"month","interval_count":1,"livemode":false,"metadata":[],"meter":null,"nickname":null,"product":"prod_T9fajqot3iVMst","tiers_mode":null,"transform_usage":null,"trial_period_days":null,"usage_type":"licensed"},"price":{"id":"price_1SDMFKRzsDq04jEjkQZpu3kG","object":"price","active":true,"billing_scheme":"per_unit","created":1759310570,"currency":"aud","custom_unit_amount":null,"livemode":false,"lookup_key":null,"metadata":[],"nickname":null,"product":"prod_T9fajqot3iVMst","recurring":{"interval":"month","interval_count":1,"meter":null,"trial_period_days":null,"usage_type":"licensed"},"tax_behavior":"unspecified","tiers_mode":null,"transform_quantity":null,"type":"recurring","unit_amount":4900,"unit_amount_decimal":"4900"},"quantity":1,"subscription":"sub_1SP0tyRzsDq04jEjW9gWUxSe","tax_rates":[]}],"has_more":false,"total_count":1,"url":"/v1/subscription_items?subscription=sub_1SP0tyRzsDq04jEjW9gWUxSe"},"latest_invoice":"in_1SP0tyRzsDq04jEj3DheHgDq","livemode":false,"metadata":[],"next_pending_invoice_item_invoice":null,"on_behalf_of":null,"pause_collection":null,"payment_settings":{"payment_method_options":{"acss_debit":null,"bancontact":null,"card":{"network":null,"request_three_d_secure":"automatic"},"customer_balance":null,"konbini":null,"sepa_debit":null,"us_bank_account":null},"payment_method_types":["card"],"save_default_payment_method":"off"},"pending_invoice_item_interval":null,"pending_setup_intent":null,"pending_update":null,"plan":{"id":"price_1SDMFKRzsDq04jEjkQZpu3kG","object":"plan","active":true,"amount":4900,"amount_decimal":"4900","billing_scheme":"per_unit","created":1759310570,"currency":"aud","interval":"month","interval_count":1,"livemode":false,"metadata":[],"meter":null,"nickname":null,"product":"prod_T9fajqot3iVMst","tiers_mode":null,"transform_usage":null,"trial_period_days":null,"usage_type":"licensed"},"quantity":1,"schedule":null,"start_date":1762088458,"status":"active","test_clock":null,"transfer_data":null,"trial_end":null,"trial_settings":{"end_behavior":{"missing_payment_method":"create_invoice"}},"trial_start":null}';
        // $subscriptionData = json_decode($data,true);
        $stripeSubscriptionId = $subscriptionData['id'];
        \Log::info('subscription updated data:',[$subscriptionData]);

        // Find existing subscription in your DB
        
        $subscription = Subscription::where('stripe_id', $stripeSubscriptionId)->first();
        \Log::info('subscription updated retrieved:',[$subscription]);


        if (!$subscription) {
            \Log::warning("No matching subscription found for Stripe ID: {$stripeSubscriptionId}");
            return;
        }

        $user = $subscription->user;


        // Extract new price_id from webhook payload
        $newPriceId = $subscriptionData['items']['data'][0]['price']['id'] ?? null;
        \Log::info('subscription updated new price id:',[$newPriceId]);

        // Only record history if plan actually changed
        if ($newPriceId && $subscription->stripe_price !== $newPriceId) {
            SubscriptionHistory::create([
                'user_id' => $user->id,
                'stripe_subscription_id' => $subscription->stripe_id,
                'price_id' => $subscription->stripe_price, // old plan ID
                'start_date' => $subscription->created_at,
                'end_date' => now(),
                'reason' => 'Plan upgraded or changed', // or derive dynamically
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \Log::info("Subscription history recorded for user {$user->id}");
        }

        // ✅ Update the active subscription record
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        \Log::info('subscription updated default details : ',[$subscriptionData['default_payment_method']]);
        \Log::info('subscription updated cusotmer details : ',[$subscriptionData['default_payment_method']]);
        
        $paymentIntentId = $subscriptionData['default_payment_method'] ?? null;
        $stripeCustomerId = $subscriptionData['customer'] ?? null;
        if(!empty($paymentIntentId) && !empty($stripeCustomerId)){
        $paymentMethod = $stripe->customers->retrievePaymentMethod(
        $stripeCustomerId,
        $paymentIntentId,
        []
        );
        

        if($paymentMethod){
            \Log::info('subscription updated payment method : ',[$paymentMethod]);
            
            $last4 = $paymentMethod['card']['last4'] ?? '';
            $type = $paymentMethod['type']?? '';

            $user = User::where('stripe_id', $stripeCustomerId)->first();

            if (!$user) {
                Log::warning("No local user found for customer ID: {$stripeCustomerId}.");
            }else{
            $user->pm_type = $type;
            $user->pm_last_four = $last4;
            $user->save();
            
            Log::info("User {$user->id} payment method updated: {$type}, last4: {$last4}.");
            }
        }
        }


        return parent::handleCustomerSubscriptionUpdated($payload);
    }


    /**
     * Handle customer subscription deleted.
     */
    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        // Custom logic when subscription is deleted
        \Log::info('Subscription deleted', [$payload]);
        try {
            $subscriptionData = $payload['data']['object'];
            $user = Auth::user();
            if(!$user){
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }
            $email = $user->email;
            $firstName = $user->first_name ?? "";
            $lastName = $user->last_name ?? "";
            $user->status = "inactive";
            $user->save();
            $subscription = $user->subscription('default');

            if(!$subscription){
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found.'
                ], 404);
            }

            $planName = Plan::where('stripe_price_id',$subscription->stripe_price)->value('name');
            \Log::info("plaName : ",[$planName]);
            try {
                Mail::to($email)->send(new SubscriptionCancelledMail($firstName, $lastName, $email,$planName));
                Log::info("Welcome email successfully queued for {$email}.");
            } catch (\Exception $e) {
                Log::error("Failed to send SubscriptionCancelled to {$email}: " . $e->getMessage());
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
        return parent::handleCustomerSubscriptionDeleted($payload);
    }
    protected function handleCustomerPaymentMethodAttached(array $payload)
    {
        Log::info('💳 customer payment_method attached webhook received');
        
        try {
            $paymentMethod = $payload['data']['object'];
            $stripeCustomerId = $paymentMethod['customer'] ?? null;
            \Log::info("customer payment_method paymentMethod : ",[$paymentMethod]);
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
        }finally{
            return $this->successMethod();
        }
    }

    protected function handleCheckoutSessionCompleted(array $payload){
        \Log::info("checkout completed webhook recieved");
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
            $paymentIntentId = $session['payment_intent'] ?? null;

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
                
                if ($paymentIntentId) {
                    \Log::info('apyment intent id', [$paymentIntentId]);

                    $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

                    // Retrieve the attached PaymentMethod
                    $paymentMethodId = $paymentIntent->payment_method;
                    if ($paymentMethodId) {
                        $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);

                        $last4 = $paymentMethod->card->last4 ?? null;
                        $brand = $paymentMethod->card->brand ?? null;

                        // Update your user table
                        $user = User::where('stripe_id', $stripeCustomerId)->first();
                        if ($user) {
                            $user->pm_last_four = $last4;
                            $user->pm_type = $brand;
                            $user->save();
                        }
                    }
                } 
                }else {
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
            Log::info('🔔 invoice.paid webhook payload : ',[$invoice]);

            // $data = '{"id":"in_1SM47yRzsDq04jEjORvmm9yq","object":"invoice","account_country":"AU","account_name":"Estate Atlas","account_tax_ids":null,"amount_due":2900,"amount_overpaid":0,"amount_paid":2900,"amount_remaining":0,"amount_shipping":0,"application":null,"attempt_count":1,"attempted":true,"auto_advance":false,"automatic_tax":{"disabled_reason":null,"enabled":false,"liability":null,"provider":null,"status":null},"automatically_finalizes_at":null,"billing_reason":"subscription_create","collection_method":"charge_automatically","created":1761385874,"currency":"aud","custom_fields":null,"customer":"cus_TIfPVFoygmEZhL","customer_address":null,"customer_email":"jackmartintest@gmail.com","customer_name":null,"customer_phone":null,"customer_shipping":null,"customer_tax_exempt":"none","customer_tax_ids":[],"default_payment_method":null,"default_source":null,"default_tax_rates":[],"description":null,"discounts":[],"due_date":null,"effective_at":1761385874,"ending_balance":0,"footer":null,"from_invoice":null,"hosted_invoice_url":"https://invoice.stripe.com/i/acct_1SCKIsRzsDq04jEj/test_YWNjdF8xU0NLSXNSenNEcTA0akVqLF9USWZTdFhhaTlxZ2w3MXh3WXAwYThUbGp6cWpkQWVhLDE1MTkyNjY3OA0200ibnaxBbb?s=ap","invoice_pdf":"https://pay.stripe.com/invoice/acct_1SCKIsRzsDq04jEj/test_YWNjdF8xU0NLSXNSenNEcTA0akVqLF9USWZTdFhhaTlxZ2w3MXh3WXAwYThUbGp6cWpkQWVhLDE1MTkyNjY3OA0200ibnaxBbb/pdf?s=ap","issuer":{"type":"self"},"last_finalization_error":null,"latest_revision":null,"lines":{"object":"list","data":[{"id":"il_1SM47yRzsDq04jEj0mlar8kA","object":"line_item","amount":2900,"currency":"aud","description":"1 × basic_monthly (at $29.00 / month)","discount_amounts":[],"discountable":true,"discounts":[],"invoice":"in_1SM47yRzsDq04jEjORvmm9yq","livemode":false,"metadata":[],"parent":{"invoice_item_details":null,"subscription_item_details":{"invoice_item":null,"proration":false,"proration_details":{"credited_items":null},"subscription":"sub_1SM47yRzsDq04jEjeEkrALcz","subscription_item":"si_TIfSMGGlaRElHZ"},"type":"subscription_item_details"},"period":{"end":1764064274,"start":1761385874},"pretax_credit_amounts":[],"pricing":{"price_details":{"price":"price_1SDMAnRzsDq04jEjj80UfhHp","product":"prod_T9fVkZNwcjt8r5"},"type":"price_details","unit_amount_decimal":"2900"},"quantity":1,"taxes":[]}],"has_more":false,"total_count":1,"url":"/v1/invoices/in_1SM47yRzsDq04jEjORvmm9yq/lines"},"livemode":false,"metadata":[],"next_payment_attempt":null,"number":"IZ5DYVKE-0001","on_behalf_of":null,"parent":{"quote_details":null,"subscription_details":{"metadata":[],"subscription":"sub_1SM47yRzsDq04jEjeEkrALcz"},"type":"subscription_details"},"payment_settings":{"default_mandate":null,"payment_method_options":{"acss_debit":null,"bancontact":null,"card":{"request_three_d_secure":"automatic"},"customer_balance":null,"konbini":null,"sepa_debit":null,"us_bank_account":null},"payment_method_types":["card"]},"period_end":1761385874,"period_start":1761385874,"post_payment_credit_notes_amount":0,"pre_payment_credit_notes_amount":0,"receipt_number":null,"rendering":null,"shipping_cost":null,"shipping_details":null,"starting_balance":0,"statement_descriptor":null,"status":"paid","status_transitions":{"finalized_at":1761385874,"marked_uncollectible_at":null,"paid_at":1761385876,"voided_at":null},"subtotal":2900,"subtotal_excluding_tax":2900,"test_clock":null,"total":2900,"total_discount_amounts":[],"total_excluding_tax":2900,"total_pretax_credit_amounts":[],"total_taxes":[],"webhooks_delivered_at":null}';
            
            if($invoice){
                if (!isset($invoice['status']) && $invoice['status'] !== 'paid') {
                    Log::warning('Invoice not paid');
                    return $this->successMethod();
                }
                 
                if (!isset($invoice['customer'])) {
                    Log::warning('missing customer ID. Ignoring.');
                    return $this->successMethod();
                }
                $stripeCustomerId = $invoice['customer'];
                $user = User::where('stripe_id',$stripeCustomerId)->first();
                if (!$user) {
                    Log::warning("No user found with stripe_id: {$stripeCustomerId}");
                    return $this->successMethod();
                }
                        // Determine if it's a subscription invoice or one-time
                $isSubscription = !empty($invoice['parent']['subscription_details']['subscription']) || in_array($invoice['billing_reason'] ?? '', ['subscription_create', 'subscription_cycle', 'subscription_update']) ||!empty($invoice['parent']['invoice_item_details']['subscription'])  ;
                if($isSubscription){
                    $sub_id = $invoice['parent']['subscription_details']['subscription'] ??$invoice['parent']['invoice_item_details']['subscription'] ?? null;
                    $type = 'subscription_payment';
                    $subscription = $user->subscriptions()->where('stripe_id',$sub_id)->first();
                    $subscriptionId = $subscription->id ?? null;
                    $plan = Plan::where('stripe_    price_id',$priceId)->first();
                    $productId = $plan->id ?? null; 
                } else{
                    $type = 'one_time_purchase';
                    $priceId = $invoice['lines']['data'][0]['price']['id'] ?? null;
                    $product = Products::where('price_id',$priceId)->first();
                    $productId = $product->id ?? null;
                }
                Log::info('🔔 invoice.paid webhook payload isSubscribed : ',[$invoice]);
                Log::info([$sub_id]);
                Log::info([$type]);
                Log::info([$subscription]);
                Log::info([$subscriptionId]);
                
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
