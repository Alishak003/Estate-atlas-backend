<?php

namespace App\Jobs\Contact;

use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\Contact\ContactFormSubmitted;
use App\Models\Contact;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendContactEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $contact;

    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
    }

    public function handle(): void
    {
        // Send email to a specific address
        $recipientEmail = 'alshahed.cse@gmail.com'; // Specify the email address

        // Queue the email with the contact details
        Mail::to($recipientEmail)->queue(new ContactFormSubmitted($this->contact));
    }
}
