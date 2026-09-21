<?php

declare(strict_types=1);

/**
 * Regression: widget appearance settings "didn't save".
 *
 * The settings page sent its page rules and allowed domains as newline text
 * instead of arrays (two Inertia transform() calls, the second replacing the
 * first). The server correctly refused the whole save — so the valid appearance
 * changes were discarded with it — and the page rendered no errors, so every
 * change looked ignored and came back as defaults on reload. These tests pin the
 * payload the page sends, the full round trip through to the public widget
 * config, and a refusal a person can actually read.
 */

use App\Models\ChatWidget;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Umarketingit');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'Chatbot', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();
});

/** Everything the settings page submits, shaped as the fixed page shapes it. */
function widgetSettingsPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Chatbot',
        'description' => '',
        'theme' => ['title' => 'Chat with us', 'company' => 'Umarketingit', 'accent' => '#294294', 'position' => 'bottom-left'],
        'settings' => [
            'teaser' => 'Hi Welcome to U marketing and IT', 'teaser_b' => '', 'teaser_delay' => 0, 'mode' => 'bot',
            'experiment' => '', 'variant' => '', 'fallback_contact' => '', 'suggested_questions' => [],
        ],
        'consent' => ['required' => false, 'message' => '', 'privacy_url' => ''],
        'targeting' => [
            'include' => ['/cybersecurity', '/services/*'], 'exclude' => ['/careers', '/privacy'],
            'visitor' => 'all', 'delay_seconds' => 1, 'scroll_percent' => 1, 'exit_intent' => false,
        ],
        'business_hours' => ['timezone' => 'UTC', 'closed_message' => '', 'days' => []],
        'allowed_domains' => [],
    ], $overrides);
}

it('saves the appearance a person set and shows it again after a reload', function () {
    $this->actingAs($this->owner)
        ->patch(route('chat.widgets.update', $this->widget), widgetSettingsPayload())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Widget updated.');

    $this->actingAs($this->owner)
        ->get(route('chat.widgets.edit', $this->widget))
        ->assertInertia(fn ($page) => $page
            ->where('widget.theme.company', 'Umarketingit')
            ->where('widget.theme.accent', '#294294')
            ->where('widget.theme.position', 'bottom-left')
            ->where('widget.settings.teaser', 'Hi Welcome to U marketing and IT')
            ->where('widget.settings.teaser_delay', 0)
            ->where('widget.targeting.include', ['/cybersecurity', '/services/*'])
            ->where('widget.targeting.exclude', ['/careers', '/privacy'])
            ->where('widget.targeting.delay_seconds', 1)
            ->where('widget.targeting.scroll_percent', 1));
});

it('serves the saved appearance to the widget on the customer website', function () {
    $this->actingAs($this->owner)->patch(route('chat.widgets.update', $this->widget), widgetSettingsPayload());

    $config = $this->getJson(route('public.chat.config', $this->widget->public_key))->assertOk()->json();

    expect($config['theme'])->toMatchArray(['company' => 'Umarketingit', 'accent' => '#294294', 'position' => 'bottom-left'])
        ->and($config['teaser'])->toBe('Hi Welcome to U marketing and IT')
        ->and($config['targeting']['include'])->toBe(['/cybersecurity', '/services/*']);
});

it('refuses page rules sent as text, names the fields plainly, and keeps nothing', function () {
    // Exactly what the broken page sent: the lists still as textarea text.
    $broken = widgetSettingsPayload(['allowed_domains' => '', 'targeting' => ['include' => "/cybersecurity\n/services/*", 'exclude' => "/careers\n/privacy"]]);

    $this->actingAs($this->owner)
        ->patch(route('chat.widgets.update', $this->widget), $broken)
        ->assertSessionHasErrors([
            'targeting.include' => 'The "only these pages" list field must be an array.',
            'targeting.exclude' => 'The "never these pages" list field must be an array.',
        ]);

    // The valid appearance changes are refused along with the rest: this is why
    // the page reloaded with its defaults.
    expect($this->widget->refresh()->theme['accent'] ?? null)->not->toBe('#294294');
});

it('explains a malformed accent colour in words, not a payload key', function () {
    $this->actingAs($this->owner)
        ->patch(route('chat.widgets.update', $this->widget), widgetSettingsPayload(['theme' => ['accent' => '#29429']]))
        ->assertSessionHasErrors(['theme.accent' => 'The accent colour must be a hex colour like #0bb39e.']);
});
