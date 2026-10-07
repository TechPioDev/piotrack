<?php

declare(strict_types=1);

/**
 * Buttons that open a link (CHAT-092, CHAT-093).
 *
 * Asked for by the owner: "a custom Google map or Teams link, or our own
 * booking form - when the user clicks Book a meeting, a form opens like Teams
 * and we do the rest from our end." The chat could only book through its own
 * calendar. A message can now carry a button to any https link, and booking
 * can hand over the owner's own booking page instead of offering times.
 */

use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Models\Lead;
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

const BOOKINGS_LINK = 'https://outlook.office.com/book/PioManage@piomanage.test/';

/** Put a conversation on the widget as it is stored. */
function useLinkFlow($test, array $nodes, string $start): void
{
    $test->widget->forceFill(['flow' => ['start' => $start, 'nodes' => $nodes]])->save();
}

/** Start a chat and send the answers; returns every reply, the first being the opening. */
function chatThrough($test, array $answers = []): array
{
    $replies = [$test->postJson("/wc/{$test->key}/conversations", ['page' => 'https://piomanage.test/'])->assertOk()->json()];
    foreach ($answers as $answer) {
        $replies[] = $test->postJson("/wc/{$test->key}/conversations/{$replies[0]['token']}/messages", $answer)->assertOk()->json();
    }

    return $replies;
}

function liveBookingPage($test): BookingPage
{
    app(CurrentOrganization::class)->set($test->org);
    $page = BookingPage::create(['name' => 'Discovery call', 'slug' => 'discovery-call', 'duration_minutes' => 30, 'is_active' => true]);
    app(CurrentOrganization::class)->forget();

    return $page;
}

/*
 * A button on a message.
 */

it('puts a button that opens a link under a message, and keeps it when the chat is reopened', function () {
    useLinkFlow($this, [
        'find_us' => ['type' => 'message', 'text' => 'Here is where to find us.', 'button' => 'Open the map', 'url' => 'https://maps.google.com/?q=PioManage', 'next' => 'in_email'],
        'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Your email?', 'next' => 'done'],
        'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
    ], 'find_us');

    [$opening] = chatThrough($this);

    expect($opening['messages'][0])->toMatchArray([
        'body' => 'Here is where to find us.',
        'link' => ['label' => 'Open the map', 'url' => 'https://maps.google.com/?q=PioManage'],
    ]);
    // The question after it has no button of its own.
    expect($opening['messages'][1])->not->toHaveKey('link');

    // Closing the chat and coming back shows the same button.
    $again = $this->getJson("/wc/{$this->key}/conversations/{$opening['token']}/poll?since=0")->assertOk()->json('messages');
    expect($again[0]['link'])->toBe(['label' => 'Open the map', 'url' => 'https://maps.google.com/?q=PioManage']);
});

it('never puts anything but a real https link in front of a visitor', function () {
    foreach (['javascript:alert(1)', 'http://piomanage.test/plain', 'data:text/html,<b>x</b>', 'https://', 'not a link'] as $bad) {
        useLinkFlow($this, [
            'say' => ['type' => 'message', 'text' => 'Look.', 'button' => 'Open', 'url' => $bad, 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
        ], 'say');

        // The line is still said; it simply has no button.
        [$opening] = chatThrough($this);
        expect($opening['messages'][0]['body'])->toBe('Look.')
            ->and($opening['messages'][0])->not->toHaveKey('link');
    }
});

it('will not publish a button that leads nowhere', function () {
    $flow = fn (array $message) => ['start' => 'say', 'nodes' => [
        'say' => ['type' => 'message', 'text' => 'Look.', 'next' => 'done', ...$message],
        'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
    ]];

    app(CurrentOrganization::class)->set($this->org);
    $validator = app(ChatFlowValidator::class);
    $results = [
        'no link' => $validator->validate($flow(['button' => 'Open the map'])),
        'http' => $validator->validate($flow(['button' => 'Open', 'url' => 'http://piomanage.test/'])),
        'script' => $validator->validate($flow(['button' => 'Open', 'url' => 'javascript:alert(1)'])),
        'long label' => $validator->validate($flow(['button' => str_repeat('a', 41), 'url' => 'https://piomanage.test/'])),
        'fine' => $validator->validate($flow(['button' => 'Open the map', 'url' => 'https://maps.google.com/?q=PioManage'])),
        'plain' => $validator->validate($flow([])),
    ];
    app(CurrentOrganization::class)->forget();

    expect(collect($results)->map(fn (array $r) => $r['valid'])->all())->toBe([
        'no link' => false, 'http' => false, 'script' => false, 'long label' => false, 'fine' => true, 'plain' => true,
    ]);
});

/*
 * Booking through the owner's own page.
 */

/** Name, email, then a booking step set to open a link, then a meeting ending - as the builder makes it. */
function bookByLink(array $booking, array $ending = []): array
{
    return [
        'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Your email?', 'next' => 'book'],
        'book' => ['type' => 'booking', 'text' => 'Book a time with us:', 'next' => 'end_meeting', 'fallback' => 'end_meeting', ...$booking],
        'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Great, pick a time that suits you.', ...$ending],
    ];
}

it('hands over the owner\'s own booking page instead of offering times', function () {
    liveBookingPage($this); // there, and deliberately not used
    useLinkFlow($this, bookByLink(['mode' => 'link', 'link_to' => 'custom', 'url' => BOOKINGS_LINK, 'button' => 'Book on Teams']), 'in_email');

    [, $final] = chatThrough($this, [['value' => 'ram@client.test']]);

    $said = collect($final['messages']);
    // One line, with the button - and the conversation is over. No times were
    // offered, the ending did not say "pick a time" again, and the link is not
    // handed over a second time under a different name.
    expect($final['done'])->toBeTrue()
        ->and($said)->toHaveCount(1)
        ->and($said[0]['body'])->toBe('Book a time with us:')
        ->and($said[0]['link'])->toBe(['label' => 'Book on Teams', 'url' => BOOKINGS_LINK])
        ->and($final)->not->toHaveKey('booking_url')
        ->and($final['node'])->toBeNull();

    // Nothing was booked on our side; the lead is saved and counted as a meeting offered.
    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', chatToken($final, $this));
    expect(Booking::withoutGlobalScope('tenant')->count())->toBe(0)
        ->and(Lead::withoutGlobalScope('tenant')->where('email', 'ram@client.test')->count())->toBe(1)
        ->and(ChatEvent::withoutGlobalScope('tenant')->where('chat_conversation_id', $conversation->id)->where('type', 'meeting')->count())->toBe(1);
});

/** The token of the one conversation in play. */
function chatToken(array $reply, $test): string
{
    return (string) ($reply['token'] ?? ChatConversation::withoutGlobalScope('tenant')->latest('id')->value('token'));
}

it('opens our own booking form as a button when asked to, and steps aside when there is none', function () {
    useLinkFlow($this, bookByLink(['mode' => 'link', 'link_to' => 'page', 'button' => 'Open the booking form']), 'in_email');

    // No booking page is live: the step steps aside, and the ending says what happens instead.
    [, $without] = chatThrough($this, [['value' => 'a@client.test']]);
    expect(collect($without['messages'])->pluck('body')->all())->toBe(['One of the team will email you shortly to arrange a time.']);

    liveBookingPage($this);
    [, $with] = chatThrough($this, [['value' => 'b@client.test']]);

    expect($with['messages'][0]['link']['label'])->toBe('Open the booking form')
        ->and($with['messages'][0]['link']['url'])->toEndWith('/b/discovery-call')
        ->and($with['messages'])->toHaveCount(1);
    // And that link really is the booking form.
    $this->get($with['messages'][0]['link']['url'])->assertOk();
});

it('still offers times in the chat when the booking step is left as it was', function () {
    liveBookingPage($this);
    useLinkFlow($this, bookByLink([]), 'in_email');

    [, $offer] = chatThrough($this, [['value' => 'ram@client.test']]);

    expect($offer['done'])->toBeFalse()
        ->and($offer['node']['type'])->toBe('choice')
        ->and(collect($offer['node']['options'])->last()['id'])->toBe('none')
        ->and($offer['messages'][0])->not->toHaveKey('link');
});

it('ends a conversation on the owner\'s own booking link, with their words and their button', function () {
    useLinkFlow($this, [
        'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Your email?', 'next' => 'end_meeting'],
        'end_meeting' => [
            'type' => 'end', 'outcome' => 'meeting', 'text' => 'Great - book a slot that suits you.',
            'link_to' => 'custom', 'url' => BOOKINGS_LINK, 'button' => 'Book with our team',
        ],
    ], 'in_email');

    // No booking page of ours exists, and none is needed.
    [, $final] = chatThrough($this, [['value' => 'ram@client.test']]);

    expect($final['done'])->toBeTrue()
        ->and(collect($final['messages'])->pluck('body')->all())->toBe(['Great - book a slot that suits you.'])
        ->and($final['booking_url'])->toBe(BOOKINGS_LINK)
        ->and($final['booking_label'])->toBe('Book with our team')
        ->and(Lead::withoutGlobalScope('tenant')->where('email', 'ram@client.test')->count())->toBe(1);
});

it('lets the ending\'s button be worded by the owner when it opens our booking form', function () {
    liveBookingPage($this);
    useLinkFlow($this, [
        'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Your email?', 'next' => 'end_meeting'],
        'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Great.', 'button' => 'See free times'],
    ], 'in_email');

    [, $final] = chatThrough($this, [['value' => 'ram@client.test']]);

    expect($final['booking_url'])->toEndWith('/b/discovery-call')
        ->and($final['booking_label'])->toBe('See free times');
});

it('checks a booking link before it can go live, and stops warning about a booking page that is not needed', function () {
    app(CurrentOrganization::class)->set($this->org);
    $validator = app(ChatFlowValidator::class);
    $check = fn (array $booking, array $ending = []) => $validator->validate(['start' => 'in_email', 'nodes' => bookByLink($booking, $ending)]);

    $ownLinks = $check(['mode' => 'link', 'link_to' => 'custom', 'url' => BOOKINGS_LINK], ['link_to' => 'custom', 'url' => BOOKINGS_LINK]);
    $missing = $check(['mode' => 'link', 'link_to' => 'custom', 'url' => '']);
    $plainHttp = $check([], ['link_to' => 'custom', 'url' => 'http://bookings.piomanage.test/']);
    $ours = $check([]);
    app(CurrentOrganization::class)->forget();

    // With their own links there is nothing of ours to warn about.
    expect($ownLinks['valid'])->toBeTrue()
        ->and($ownLinks['warnings'])->toBe([])
        ->and($missing['valid'])->toBeFalse()
        ->and(collect($missing['errors'])->pluck('node')->all())->toContain('book')
        ->and($plainHttp['valid'])->toBeFalse()
        ->and(collect($plainHttp['errors'])->pluck('node')->all())->toContain('end_meeting')
        // Using our booking page with none live still warns, on both steps.
        ->and($ours['valid'])->toBeTrue()
        ->and(collect($ours['warnings'])->pluck('node')->all())->toBe(['book', 'end_meeting']);
});
