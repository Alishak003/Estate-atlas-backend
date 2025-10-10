<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\helpSupport;
use App\Mail\HelpSupportMail;
use Illuminate\Support\Facades\Mail;

class SendHelpSupportEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $helpSupport;

    public function __construct(helpSupport $helpSupport)
    {
        $this->helpSupport = $helpSupport;
    }

    public function handle()
    {
        // You can change the recipient as needed
        Mail::to(config('mail.from.address'))->send(new HelpSupportMail($this->helpSupport));
    }
}
