<?php

declare(strict_types=1);

/**
 * Earlier versions of a conversation, and putting one back.
 *
 * The builder could undo while the page was open and nothing afterwards: close
 * the tab and yesterday's conversation was gone for good. Every save now keeps
 * what it replaced, and a version can be restored into the editor - as a draft,
 * because restoring is a decision about the editor and publishing is a separate
 * decision about visitors.
 */

use App\Models\ChatFlowVersion;
use App\Models\ChatWidget;
use App\Models\NotificationChannel;
use App\Notifications\ChatVisitorWaitingNotification;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'draft']);
    app(CurrentOrganization::class)->forget();
});

function aFlow(string $greeting): array
{
    return [
        'start' => 'hello',
        'nodes' => [
            'hello' => ['type' => 'message', 'text' => $greeting, 'next' => 'ask'],
            'ask' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Your email?', 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
        ],
    ];
}

function saveFlow($test, array $flow, bool $publish = false)
{
    return $test->actingAs($test->owner)->put(route('chat.flow.update', $test->widget), ['flow' => $flow, 'publish' => $publish]);
}

it('keeps what each save replaced, and says who and when', function () {
    saveFlow($this, aFlow('First words'))->assertRedirect();
    saveFlow($this, aFlow('Second words'))->assertRedirect();
    saveFlow($this, aFlow('Third words'))->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    $versions = ChatFlowVersion::query()->where('chat_widget_id', $this->widget->id)->orderBy('id')->get();
    app(CurrentOrganization::class)->forget();

    // The first save had nothing to replace; the next two did.
    expect($versions)->toHaveCount(2);
    expect($versions[0]->flow['nodes']['hello']['text'])->toBe('First words');
    expect($versions[1]->flow['nodes']['hello']['text'])->toBe('Second words');
    expect($versions[0]->saved_by)->toBe($this->owner->id);
    expect($versions[0]->steps)->toBe(3);
});

it('does not keep a version when nothing actually changed', function () {
    saveFlow($this, aFlow('Same words'));
    saveFlow($this, aFlow('Same words'));
    saveFlow($this, aFlow('Same words'));

    app(CurrentOrganization::class)->set($this->org);
    $count = ChatFlowVersion::query()->where('chat_widget_id', $this->widget->id)->count();
    app(CurrentOrganization::class)->forget();

    expect($count)->toBe(1);
});

it('offers the history on the builder page', function () {
    saveFlow($this, aFlow('First words'));
    saveFlow($this, aFlow('Second words'));

    $this->actingAs($this->owner)
        ->get(route('chat.flow.edit', $this->widget))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('history.0.steps', 3)
            ->where('history.0.author', $this->owner->name));
});

it('puts an earlier version back, as a draft, and keeps the one it replaced', function () {
    saveFlow($this, aFlow('First words'));
    saveFlow($this, aFlow('Second words'));

    app(CurrentOrganization::class)->set($this->org);
    $first = ChatFlowVersion::query()->where('chat_widget_id', $this->widget->id)->orderBy('id')->first();
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->post(route('chat.flow.restore', [$this->widget, $first]))
        ->assertRedirect();

    app(CurrentOrganization::class)->set($this->org);
    $widget = ChatWidget::query()->find($this->widget->id);
    $versions = ChatFlowVersion::query()->where('chat_widget_id', $this->widget->id)->count();
    app(CurrentOrganization::class)->forget();

    expect($widget->flow['nodes']['hello']['text'])->toBe('First words');
    // Two saves left one version ("First words"); restoring it kept the
    // "Second words" it replaced, so the restore is itself undoable.
    expect($versions)->toBe(2);
    // A restore never puts a conversation live by itself.
    expect($widget->status)->toBe('draft');
});

it('refuses to restore something broken onto a live chat', function () {
    saveFlow($this, aFlow('First words'), publish: true);
    saveFlow($this, aFlow('Second words'));

    app(CurrentOrganization::class)->set($this->org);
    // A version that would strand visitors: a question with nowhere to go.
    $broken = ChatFlowVersion::create([
        'chat_widget_id' => $this->widget->id,
        'flow' => ['start' => 'hello', 'nodes' => ['hello' => ['type' => 'message', 'text' => 'Hi', 'next' => 'nowhere']]],
        'steps' => 1,
    ]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->post(route('chat.flow.restore', [$this->widget, $broken]))
        ->assertSessionHasErrors('flow');

    app(CurrentOrganization::class)->set($this->org);
    expect(ChatWidget::query()->find($this->widget->id)->flow['nodes']['hello']['text'])->toBe('Second words');
    app(CurrentOrganization::class)->forget();
});

it('will not restore one widget’s version onto another', function () {
    saveFlow($this, aFlow('First words'));
    saveFlow($this, aFlow('Second words'));

    app(CurrentOrganization::class)->set($this->org);
    $other = ChatWidget::create(['name' => 'Another site', 'status' => 'draft']);
    $version = ChatFlowVersion::query()->where('chat_widget_id', $this->widget->id)->first();
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)
        ->post(route('chat.flow.restore', [$other, $version]))
        ->assertNotFound();
});

it('keeps the last two dozen versions, not every save ever made', function () {
    for ($i = 0; $i <= ChatFlowVersion::KEEP + 4; $i++) {
        saveFlow($this, aFlow('Words '.$i));
    }

    app(CurrentOrganization::class)->set($this->org);
    $versions = ChatFlowVersion::query()->where('chat_widget_id', $this->widget->id)->orderBy('id')->get();
    app(CurrentOrganization::class)->forget();

    expect($versions)->toHaveCount(ChatFlowVersion::KEEP);
    // The oldest went, the newest stayed.
    expect($versions->last()->flow['nodes']['hello']['text'])->toBe('Words '.(ChatFlowVersion::KEEP + 3));
});

it('pages the team’s own channels when a visitor is left waiting, not just email', function () {
    Http::fake(['*' => Http::response('ok')]);

    app(CurrentOrganization::class)->set($this->org);
    NotificationChannel::create(['kind' => 'teams', 'url' => 'https://93.184.216.34/teams-hook', 'is_active' => true]);
    app(CurrentOrganization::class)->forget();

    app(NotificationDispatcher::class)->toOrganizationOwners(
        $this->org,
        new ChatVisitorWaitingNotification(42, 'Nobody was online.'),
    );

    // It used to be mail and an in-app bell only, which a team living in Teams
    // or Slack never sees.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'teams-hook')
        && str_contains(json_encode($request->data()), 'waiting'));
});
