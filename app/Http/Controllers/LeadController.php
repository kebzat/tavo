<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadRequest;
use App\Mail\LeadReceived;
use App\Models\Lead;
use App\Settings\ContactSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LeadController extends Controller
{
    public function __invoke(LeadRequest $request, ContactSettings $contact): RedirectResponse|JsonResponse
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

        // Odeslání na pozadí si poděkování vykreslí samo, přesměrování by ho
        // připravilo o smysl. Prohlížeč bez JS dostane přesměrování jako dřív.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->to(route('home').'#kontakt')
            ->with('lead_sent', true);
    }
}
