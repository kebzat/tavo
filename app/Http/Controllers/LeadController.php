<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadRequest;
use App\Mail\LeadReceived;
use App\Models\Lead;
use App\Settings\ContactSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LeadController extends Controller
{
    public function __invoke(LeadRequest $request, ContactSettings $contact): RedirectResponse
    {
        $lead = Lead::create($request->safe()->only([
            'name', 'company', 'email', 'phone', 'topic', 'budget', 'message',
        ]) + [
            'status' => 'new',
            'source_url' => $request->headers->get('referer'),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        $recipients = $contact->recipientEmails() ?: array_filter([config('mail.from.address')]);

        // Odeslání e-mailu nesmí shodit odeslání formuláře — poptávka je už v DB.
        try {
            Mail::to($recipients)->send(new LeadReceived($lead));
        } catch (\Throwable $e) {
            Log::error('Nepodařilo se odeslat notifikaci o poptávce', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->to(route('home').'#kontakt')
            ->with('lead_sent', true);
    }
}
