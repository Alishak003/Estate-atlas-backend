<?php

namespace App\Http\Controllers;

use Stripe\Webhook;
use Stripe\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeUserMail;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class WebhookController extends CashierController
{
    

    public function handleCustomerSubscriptionCreated(array $payload)
    {
        // Custom logic when subscription is created
        \Log::info('Subscription created', $payload);

        return $this->successMethod();
    }

    /**
     * Handle customer subscription updated.
     */
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

    /**
     * Handle invoice payment succeeded.
     */
    public function handleInvoicePaymentSucceeded(array $payload)
    {
        // Custom logic when payment succeeds
        \Log::info('Payment succeeded', $payload);

        return $this->successMethod();
    }

    /**
     * Handle invoice payment failed.
     */
    public function handleInvoicePaymentFailed(array $payload)
    {
        // Custom logic when payment fails
        \Log::info('Payment failed', $payload);

        return $this->successMethod();
    }

    protected function handleCheckoutSessionCompleted(array $payload){
        Log::info('🔔 checkout.session.completed webhook received');

        $session = $payload['data']['object'];
        // $email = $session['customer_details']['email'] ?? null;
        $email = 'alishakhanstack@gmail.com';
        if (!$email) {
            Log::warning('Email not found in checkout.session.completed payload for session ID: ' . ($session['id'] ?? 'N/A'));
            return $this->successMethod();
        }

        Log::info('Customer email : '.$email);
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

        return $this->successMethod();
    }

     protected function handleInvoicePaid(array $payload)
    {
        Log::info('🔔 invoice.paid webhook received');
        $invoice = $payload['data']['object'];

        // Your custom logic for when an invoice is paid (e.g., updating user status)
        // Ensure you return success method to acknowledge the webhook
        return $this->successMethod();
    }
}
