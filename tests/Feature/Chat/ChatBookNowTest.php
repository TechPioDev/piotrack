<?php

declare(strict_types=1);

/**
 * What a visitor actually gets when they say yes to a call.
 *
 * There are two ways a conversation can end in a meeting, and they behave
 * differently: the booking step picks a time inside the chat, while a template's
 * "Yes, book it" ending hands over the booking page. Either way the visitor must
 * be given a way to book - being told to "pick a time" with nothing to pick is
 * the one outcome that must never happen.
 */

use App\Models\BookingPage;
use App\Models\ChatWidget;
use App\Services\Chat\ChatFlowTemplates;
use App\Services\Chat\ChatFlowValidator;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();
    $this->key = $this->widget->public_key;
});

/** The shortest flow that offers a call and ends in a meeting, as every template does. */
function offerACall(): array
{
    return [
        'start' => 'in_email',
        'nodes' => [
            'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Your email?', 'next' => 'q_meeting'],
            'q_meeting' => [
                'type' => 'choice',
                'text' => 'Would you like a call?',
                'field' => 'wants_meeting',
                'options' => [
                    ['id' => 'yes', 'label' => 'Yes, book it', 'next' => 'end_meeting'],
                    ['id' => 'no', 'label' => 'Not right now', 'next' => 'end_lead'],
                ],
            ],
            'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Great, pick a time for your call.'],
            'end_lead' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks, we will be in touch.'],
        ],
    ];
}

it('hands over a booking page when the visitor says yes to a call', function () {
    app(CurrentOrganization::class)->set($this->org);
    BookingPage::create([
        'name' => 'Discovery call',
        'slug' => 'discovery-call',
        'duration_minutes' => 30,
        'is_active' => true,
    ]);
    app(CurrentOrganization::class)->forget();

    app(CurrentOrganization::class)->set($this->org);
    $this->widget->forceFill(['flow' => offerACall()])->save();
    app(CurrentOrganization::class)->forget();

    $token = $this->postJson("/wc/{$this->key}/conversations", ['page' => 'https://piomanage.test/'])->assertOk()->json('token');
    $this->postJson("/wc/{$this->key}/conversations/{$token}/messages", ['value' => 'someone@piomanage.test'])->assertOk();
    $final = $this->postJson("/wc/{$this->key}/conversations/{$token}/messages", ['option' => 'yes'])->assertOk()->json();

    expect($final['done'])->toBeTrue();
    expect($final['booking_url'] ?? null)->toContain('/b/discovery-call');

    // And that link really is a booking form the visitor can fill in.
    $this->get($final['booking_url'])->assertOk();
});

it('tells the visitor what happens instead when no booking page is live', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->forceFill(['flow' => offerACall()])->save();
    app(CurrentOrganization::class)->forget();

    $token = $this->postJson("/wc/{$this->key}/conversations", ['page' => 'https://piomanage.test/'])->assertOk()->json('token');
    $this->postJson("/wc/{$this->key}/conversations/{$token}/messages", ['value' => 'someone@piomanage.test'])->assertOk();
    $final = $this->postJson("/wc/{$this->key}/conversations/{$token}/messages", ['option' => 'yes'])->assertOk()->json();

    // No link to give, so the visitor is told what will happen rather than
    // being left with an instruction they cannot follow.
    expect($final['booking_url'] ?? null)->toBeNull();
    expect(collect($final['messages'])->pluck('body')->implode(' '))->toContain('email you shortly to arrange a time');
});

it('warns the owner while they build that a meeting has nowhere to go', function () {
    $validator = app(ChatFlowValidator::class);

    app(CurrentOrganization::class)->set($this->org);
    $without = $validator->validate(offerACall());

    BookingPage::create(['name' => 'Discovery call', 'slug' => 'discovery-call', 'duration_minutes' => 30, 'is_active' => true]);
    $with = $validator->validate(offerACall());
    app(CurrentOrganization::class)->forget();

    expect($without['valid'])->toBeTrue();
    expect(collect($without['warnings'])->pluck('message')->implode(' '))->toContain('no booking page is live');
    expect(collect($with['warnings'])->pluck('message')->implode(' '))->not->toContain('no booking page is live');
});

it('offers times inside the chat in every template that promises a meeting', function () {
    $templates = app(ChatFlowTemplates::class);
    $promisesMeeting = [];
    $picksATime = [];

    foreach ($templates->catalog() as $template) {
        $flow = $templates->flow($template['key']);
        foreach ($flow['nodes'] ?? [] as $node) {
            if (($node['type'] ?? '') === 'end' && in_array($node['outcome'] ?? '', ['meeting', 'booked'], true)) {
                $promisesMeeting[$template['key']] = true;
            }
            if (($node['type'] ?? '') === 'booking') {
                $picksATime[$template['key']] = true;
            }
        }
    }

    expect(array_keys($promisesMeeting))->toBe(array_keys($picksATime));
    expect($picksATime)->toHaveCount(10);
});

it('asks for a time once, never twice, in the flow that already booked in chat', function () {
    $flow = app(ChatFlowTemplates::class)->flow('msp_qualification');

    $bookingSteps = collect($flow['nodes'])->where('type', 'booking');

    expect($bookingSteps)->toHaveCount(1);
    // Its fallback is still the ending that hands over the booking page.
    expect($flow['nodes'][$bookingSteps->keys()->first()]['fallback'])->toBe('end_meeting');
});
