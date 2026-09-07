<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NotificationChannel;
use App\Services\OrganizationService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function __construct(
        private OrganizationService $organizations,
        private CurrentOrganization $currentOrganization,
    ) {}

    public function edit(): Response
    {
        $organization = $this->currentOrganization->get();

        return Inertia::render('settings/organization', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            // NOTIF-004/005: outbound Slack/Teams/webhook channels.
            'notification_channels' => NotificationChannel::orderBy('id')->get()->map(fn (NotificationChannel $c) => [
                'id' => $c->id, 'kind' => $c->kind, 'url' => $c->url, 'has_secret' => $c->secret !== null && $c->secret !== '', 'is_active' => $c->is_active,
            ])->all(),
            'channel_kinds' => NotificationChannel::KINDS,
        ]);
    }

    /** NOTIF-004/005: add an outbound notification channel. */
    public function storeChannel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(NotificationChannel::KINDS)],
            'url' => ['required', 'url', 'starts_with:https://', 'max:500'],
            'secret' => ['nullable', 'string', 'max:100'],
        ]);

        NotificationChannel::create($data);

        return back()->with('status', __('Notification channel added — the next organization alert will post to it.'));
    }

    public function destroyChannel(NotificationChannel $channel): RedirectResponse
    {
        $channel->delete();

        return back()->with('status', __('Notification channel removed.'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $this->organizations->update($this->currentOrganization->get(), $validated['name']);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization->get();

        $request->validate([
            'name' => ['required', Rule::in([$organization->name])],
        ], [
            'name.in' => __('Please type the organization name exactly to confirm deletion.'),
        ]);

        $this->organizations->delete($organization);

        return redirect()->route('dashboard');
    }
}
