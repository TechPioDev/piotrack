<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public contact-page submissions (MSITE): stored for review, honeypot-guarded.
 * Mail forwarding comes later — there is no platform SMTP account yet.
 */
class ContactMessageController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        // Honeypot: bots fill the hidden "website" field — thank them quietly.
        if ($request->filled('website')) {
            return back()->with('status', __('Thanks — we got your message and will reply soon.'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        ContactMessage::create([
            'name' => trim($data['name']),
            'email' => Str::lower(trim($data['email'])),
            'company' => isset($data['company']) ? trim($data['company']) : null,
            'message' => $data['message'],
            'source' => 'contact-page',
        ]);

        // Platform-level: prospects have no tenant.
        $audit->platform('contact.message_received', context: ['source' => 'contact-page']);

        return back()->with('status', __('Thanks — we got your message and will reply soon.'));
    }
}
