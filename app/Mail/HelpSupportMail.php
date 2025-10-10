<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\helpSupport;

class HelpSupportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $helpSupport;

    public function __construct(helpSupport $helpSupport)
    {
        $this->helpSupport = $helpSupport;
    }

    public function build()
    {
        return $this->subject('New Help & Support Request')
            ->view('emails.help-support.request')
            ->with(['helpSupport' => $this->helpSupport]);
    }
}
