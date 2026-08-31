<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Homepage newsletter signup: real storage, honeypot-guarded, idempotent —
 * subscribing twice is a warm "you're on the list", never an error.
 */
class NewsletterController extends Controller
{
    public function subscribe(Request $request, AuditLogger $audit): RedirectResponse
    {
        // Honeypot: bots fill the hidden "website" field — thank them quietly.
        if ($request->filled('website')) {
            return back()->with('status', __("You're on the list."));
        }

        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $subscriber = NewsletterSubscriber::firstOrCreate(['email' => Str::lower(trim($data['email']))]);

        if ($subscriber->wasRecentlyCreated) {
            // Platform-level: this list belongs to no tenant.
            $audit->platform('newsletter.subscribed', context: ['source' => 'homepage']);
        }

        return back()->with('status', __("You're on the list — MSP growth insights, no spam."));
    }
}
