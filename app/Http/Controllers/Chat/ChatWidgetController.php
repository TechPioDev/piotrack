<?php

namespace App\Http\Controllers\Chat;

use App\Billing\Entitlements;
use App\Billing\Feature;
use App\Http\Controllers\Controller;
use App\Models\ChatWidget;
use App\Security\UploadScanner;
use App\Services\Chat\ChatFlowTemplates;
use App\Services\Chat\DefaultChatFlow;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for chat widgets. Authorization is enforced at the route layer
 * (can:chat.widget.manage / can:chat.view); tenant scoping via BelongsToTenant.
 */
class ChatWidgetController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Entitlements $entitlements,
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /** Removing "Powered by Piotrack" is part of white-labelling (Agency and Enterprise plans). */
    private function canHideBranding(): bool
    {
        $organization = $this->currentOrganization->get();

        return $organization !== null && $this->entitlements->feature($organization, Feature::WhiteLabel);
    }

    public function index(ChatFlowTemplates $templates): Response
    {
        $widgets = ChatWidget::query()
            ->withCount('conversations')
            ->latest()
            ->get()
            ->map(fn (ChatWidget $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'description' => $w->description,
                'status' => $w->status,
                'public_key' => $w->public_key,
                'conversations_count' => $w->conversations_count,
                'embed' => sprintf(
                    '<script src="%s" data-widget="%s" async></script>',
                    url('/widget/piotrack-chat.js'),
                    $w->public_key,
                ),
            ]);

        return Inertia::render('chat/widgets/index', [
            'widgets' => $widgets,
            // What a new widget can start from; the conversations themselves stay server-side.
            'templates' => array_map(
                fn (array $t) => ['key' => $t['key'], 'name' => $t['name'], 'category' => $t['category']],
                $templates->catalog(),
            ),
        ]);
    }

    public function store(Request $request, ChatFlowTemplates $templates): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            // CHAT-057: start from the conversation for their type of business.
            'template' => ['nullable', 'string', Rule::in(array_column($templates->catalog(), 'key'))],
        ], [
            'template.in' => 'That template no longer exists. Choose another, or start from the standard conversation.',
        ]);

        $flow = isset($data['template']) ? $templates->flow($data['template']) : null;
        unset($data['template']);

        $widget = ChatWidget::create([
            ...$data,
            'status' => 'draft',
            'flow' => $flow ?? DefaultChatFlow::definition(),
            'theme' => ['accent' => '#0bb39e', 'position' => 'bottom-right', 'title' => 'Chat with us'],
            'consent' => ['required' => false],
            'settings' => ['language' => 'en', 'mode' => 'bot'],
            'allowed_domains' => [],
        ]);

        $this->audit->log('chat.widget.created', ['name' => $widget->name], resourceType: 'chat_widget', resourceId: (string) $widget->id);

        return back()->with('status', 'Widget created.');
    }

    /** The widget's own settings: appearance, targeting, hours, consent, mode. */
    public function edit(ChatWidget $widget): Response
    {
        return Inertia::render('chat/widgets/settings', [
            'widget' => [
                'id' => $widget->id,
                'name' => $widget->name,
                'description' => $widget->description,
                'status' => $widget->status,
                'public_key' => $widget->public_key,
                'theme' => $widget->theme ?? [],
                'consent' => $widget->consent ?? [],
                'settings' => $widget->settings ?? [],
                'targeting' => $widget->targeting ?? [],
                'business_hours' => $widget->business_hours ?? [],
                'allowed_domains' => $widget->allowed_domains ?? [],
                'logo_url' => $widget->logoUrl(),
                'can_hide_branding' => $this->canHideBranding(),
                'embed' => sprintf(
                    '<script src="%s" data-widget="%s" async></script>',
                    url('/widget/piotrack-chat.js'),
                    $widget->public_key,
                ),
            ],
        ]);
    }

    public function update(Request $request, ChatWidget $widget): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'description' => 'nullable|string|max:255',
            'status' => ['sometimes', Rule::in(ChatWidget::STATUSES)],
            'theme' => 'sometimes|array',
            'theme.title' => 'nullable|string|max:60',
            'theme.company' => 'nullable|string|max:60',
            'theme.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.position' => ['nullable', Rule::in(['bottom-left', 'bottom-right'])],
            'consent' => 'sometimes|array',
            'consent.required' => 'sometimes|boolean',
            'consent.message' => 'nullable|string|max:500',
            'consent.privacy_url' => 'nullable|url|max:500',
            'settings' => 'sometimes|array',
            'settings.teaser' => 'nullable|string|max:120',
            // CHAT-041: the B teaser arms the A/B split, sticky per visitor.
            'settings.teaser_b' => 'nullable|string|max:120',
            'settings.teaser_delay' => 'nullable|integer|min:0|max:120',
            'settings.mode' => ['nullable', Rule::in(['bot', 'bot_then_human', 'live'])],
            'settings.language' => 'nullable|string|max:10',
            'settings.experiment' => 'nullable|string|max:60',
            'settings.variant' => 'nullable|string|max:60',
            'settings.fallback_contact' => 'nullable|string|max:200',
            'settings.suggested_questions' => 'sometimes|array|max:6',
            'settings.suggested_questions.*' => 'string|max:120',
            'settings.attachments' => 'sometimes|boolean',
            'settings.email_replies' => 'sometimes|boolean',
            'settings.hide_branding' => 'sometimes|boolean',
            'allowed_domains' => 'sometimes|array|max:20',
            'allowed_domains.*' => 'string|max:255',
            'routing' => 'sometimes|array',
            'routing.assignee_id' => 'nullable|integer',
            // Page + behaviour targeting (§34, §35).
            'targeting' => 'sometimes|array',
            'targeting.include' => 'sometimes|array|max:50',
            'targeting.include.*' => 'string|max:255',
            'targeting.exclude' => 'sometimes|array|max:50',
            'targeting.exclude.*' => 'string|max:255',
            'targeting.devices' => 'sometimes|array',
            'targeting.devices.*' => Rule::in(['desktop', 'mobile']),
            'targeting.visitor' => ['nullable', Rule::in(['all', 'first', 'returning'])],
            'targeting.delay_seconds' => 'nullable|integer|min:0|max:300',
            'targeting.scroll_percent' => 'nullable|integer|min:0|max:100',
            'targeting.exit_intent' => 'sometimes|boolean',
            // Business hours (§33).
            'business_hours' => 'sometimes|array',
            'business_hours.timezone' => 'nullable|string|max:64',
            'business_hours.closed_message' => 'nullable|string|max:300',
            'business_hours.days' => 'sometimes|array',
        ], [
            'theme.accent.regex' => 'The accent colour must be a hex colour like #0bb39e.',
        ], [
            // Refusals are shown to the person editing the widget, so they name
            // the fields as the settings page labels them, not as payload keys.
            'theme.title' => 'chat title',
            'theme.company' => 'company name',
            'theme.position' => 'position',
            'consent.message' => 'consent message',
            'consent.privacy_url' => 'privacy policy link',
            'settings.teaser' => 'welcome teaser',
            'settings.teaser_b' => 'teaser B',
            'settings.teaser_delay' => 'teaser delay',
            'settings.mode' => 'chat mode',
            'settings.fallback_contact' => 'fallback contact',
            'settings.suggested_questions' => 'suggested questions',
            'settings.suggested_questions.*' => 'suggested question',
            'allowed_domains' => 'allowed domains',
            'allowed_domains.*' => 'allowed domain',
            'targeting.include' => '"only these pages" list',
            'targeting.include.*' => 'page rule',
            'targeting.exclude' => '"never these pages" list',
            'targeting.exclude.*' => 'page rule',
            'targeting.visitor' => 'show to',
            'targeting.delay_seconds' => 'wait',
            'targeting.scroll_percent' => 'scroll depth',
            'business_hours.timezone' => 'timezone',
            'business_hours.closed_message' => 'closed message',
        ]);

        if (($data['settings']['hide_branding'] ?? false) && ! $this->canHideBranding()) {
            return back()->withErrors([
                'settings.hide_branding' => 'Removing "Powered by Piotrack" is included in the Agency and Enterprise plans.',
            ]);
        }

        $widget->update($data);
        $this->audit->log('chat.widget.updated', ['fields' => array_keys($data)], resourceType: 'chat_widget', resourceId: (string) $widget->id);

        return back()->with('status', 'Widget updated.');
    }

    /**
     * The widget's logo, shown in its launcher and chat header. Raster images
     * only: an uploaded SVG can carry script, and this file is served to every
     * visitor of the customer's website.
     */
    public function uploadLogo(Request $request, ChatWidget $widget, UploadScanner $scanner): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:512', 'dimensions:max_width=2000,max_height=2000'],
        ], [
            'logo.mimes' => 'The logo must be a PNG, JPG or WebP image.',
            'logo.max' => 'The logo must be 512 KB or smaller.',
            'logo.dimensions' => 'The logo must be at most 2000 × 2000 pixels.',
        ]);

        $upload = $request->file('logo');
        $scanner->scan($upload);

        $previous = $widget->logo_path;
        $path = $upload->store("org-{$widget->organization_id}/chat-logos", 'local');
        $widget->forceFill(['logo_path' => $path])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        $this->audit->log('chat.widget.logo_uploaded', ['size' => $upload->getSize()], resourceType: 'chat_widget', resourceId: (string) $widget->id);

        return back()->with('status', 'Logo uploaded.');
    }

    public function removeLogo(ChatWidget $widget): RedirectResponse
    {
        if ($widget->logo_path !== null) {
            Storage::disk('local')->delete($widget->logo_path);
            $widget->forceFill(['logo_path' => null])->save();
            $this->audit->log('chat.widget.logo_removed', [], resourceType: 'chat_widget', resourceId: (string) $widget->id);
        }

        return back()->with('status', 'Logo removed.');
    }

    public function destroy(ChatWidget $widget): RedirectResponse
    {
        $widget->delete();
        $this->audit->log('chat.widget.deleted', ['name' => $widget->name], resourceType: 'chat_widget', resourceId: (string) $widget->id);

        return back()->with('status', 'Widget deleted.');
    }
}
