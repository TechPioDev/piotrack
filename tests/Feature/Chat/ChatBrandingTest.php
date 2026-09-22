<?php

declare(strict_types=1);

/**
 * "Powered by Piotrack" can come off the chat window on the plans that
 * include white-labelling (Agency and Enterprise), and only on those.
 */

use App\Models\ChatWidget;
use App\Support\CurrentOrganization;

function brandedWidget(string $plan): array
{
    [$org, $owner] = makeOrganization("{$plan} MSP");
    subscribeOrganization($org, $plan);

    app(CurrentOrganization::class)->set($org);
    $widget = ChatWidget::create(['name' => 'Website', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();

    return [$org, $owner, $widget];
}

it('takes the branding off on a plan with white-labelling', function () {
    [, $owner, $widget] = brandedWidget('agency');

    $this->actingAs($owner)->get(route('chat.widgets.edit', $widget))
        ->assertInertia(fn ($page) => $page->where('widget.can_hide_branding', true));

    $this->actingAs($owner)->patch(route('chat.widgets.update', $widget), ['settings' => ['hide_branding' => true]])
        ->assertSessionHasNoErrors();

    $this->getJson("/wc/{$widget->public_key}/config")->assertJsonPath('branding', false);
});

it('keeps the branding on other plans, and says which plans remove it', function () {
    [, $owner, $widget] = brandedWidget('professional');

    $this->actingAs($owner)->get(route('chat.widgets.edit', $widget))
        ->assertInertia(fn ($page) => $page->where('widget.can_hide_branding', false));

    $this->actingAs($owner)->patch(route('chat.widgets.update', $widget), ['settings' => ['hide_branding' => true]])
        ->assertSessionHasErrors(['settings.hide_branding' => 'Removing "Powered by Piotrack" is included in the Agency and Enterprise plans.']);

    $this->getJson("/wc/{$widget->public_key}/config")->assertJsonPath('branding', true);
});

it('shows the branding again after a move to a plan without white-labelling', function () {
    // The widget was set to hide it on an earlier plan; the setting stays, but
    // the plan is checked on every load, so the branding comes back.
    [, , $widget] = brandedWidget('professional');
    $widget->forceFill(['settings' => ['hide_branding' => true]])->save();

    $this->getJson("/wc/{$widget->public_key}/config")->assertJsonPath('branding', true);
});

it('tells the widget whether visitors may send files', function () {
    [, , $widget] = brandedWidget('professional');
    $this->getJson("/wc/{$widget->public_key}/config")->assertJsonPath('attachments', true);

    $widget->forceFill(['settings' => ['attachments' => false]])->save();
    $this->getJson("/wc/{$widget->public_key}/config")->assertJsonPath('attachments', false);
});
