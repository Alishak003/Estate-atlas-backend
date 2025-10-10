<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\helpSupport;
use App\Jobs\SendHelpSupportEmail;

class HelpSupportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string',
            'priority' => 'required|string',
            'message' => 'required|string',
        ]);

        $helpSupport = helpSupport::create($validated);

        // Dispatch a job to send the help support details via email
        SendHelpSupportEmail::dispatch($helpSupport);

        return response()->json([
            'success' => true,
            'message' => 'Help support request submitted and email queued.',
            'data' => $helpSupport
        ]);
    }
}
