<?php

namespace App\Mail\Contact;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;

class ContactFormSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    protected Contact $contact;

    /**
     * Create a new message instance.
     */
    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contact Form Submitted',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact.form-submitted',
            with: [
                'first_name' => $this->contact->first_name,
                'last_name' => $this->contact->last_name,
                'email' => $this->contact->email,
                'phone' => $this->contact->phone,
                'message_text' => $this->contact->message,
            ]
        );
    }
    public function attachments(): array
    {
        return [];
    }
}
