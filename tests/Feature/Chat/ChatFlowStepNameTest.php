<?php

declare(strict_types=1);

/**
 * A step can be given a name of the owner's own (CHAT-089).
 *
 * A template is mostly questions, and four cards all titled "Ask a Question"
 * cannot be told apart. The name lives in the saved conversation and is for the
 * builder only: it must never reach the person chatting.
 */

use App\Models\ChatWidget;
use App\Services\Chat\ChatFlowValidator;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'draft']);
    app(CurrentOrganization::class)->forget();
});

/** @return array<string, mixed> */
function namedFlow(string $name = 'Company size'): array
{
    return [
        'start' => 'q_size',
        'nodes' => [
            'q_size' => ['type' => 'choice', 'name' => $name, 'text' => 'How many employees do you have?', 'field' => 'size', 'options' => [
                ['id' => 'small', 'label' => '1-10', 'next' => 'done'],
                ['id' => 'large', 'label' => '11+', 'next' => 'done'],
            ]],
            'done' => ['type' => 'end', 'name' => 'Thanks and goodbye', 'outcome' => 'lead', 'text' => 'Thanks!'],
        ],
    ];
}

it('keeps the names an owner gives their steps', function () {
    $this->actingAs($this->owner)
        ->put(route('chat.flow.update', $this->widget), ['flow' => namedFlow(), 'publish' => true])
        ->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($this->org);
    $saved = ChatWidget::query()->findOrFail($this->widget->id);
    app(CurrentOrganization::class)->forget();

    expect($saved->status)->toBe('active')
        ->and($saved->flow['nodes']['q_size']['name'])->toBe('Company size')
        ->and($saved->flow['nodes']['done']['name'])->toBe('Thanks and goodbye');

    // And the builder gets them back.
    $this->actingAs($this->owner)->get(route('chat.flow.edit', $this->widget))
        ->assertInertia(fn ($page) => $page->where('flow.nodes.q_size.name', 'Company size'));
});

it('never shows a step\'s name to the person chatting', function () {
    $this->actingAs($this->owner)->put(route('chat.flow.update', $this->widget), ['flow' => namedFlow('INTERNAL - qualify hard'), 'publish' => true]);
    auth()->logout();

    $key = $this->widget->refresh()->public_key;
    $start = $this->postJson("/wc/{$key}/conversations", ['page' => 'https://piomanage.test/'])->assertOk();
    $end = $this->postJson("/wc/{$key}/conversations/{$start->json('token')}/messages", ['option' => 'small'])->assertOk();

    expect($start->json('node.text'))->toBe('How many employees do you have?')
        ->and($start->getContent())->not->toContain('INTERNAL')
        ->and($end->getContent())->not->toContain('Thanks and goodbye');
});

it('will not publish a step whose name is not a short piece of text', function () {
    app(CurrentOrganization::class)->set($this->org);
    $validator = app(ChatFlowValidator::class);
    $long = $validator->validate(namedFlow(str_repeat('a', 61)));
    $odd = namedFlow();
    $odd['nodes']['q_size']['name'] = ['not', 'text'];
    $array = $validator->validate($odd);
    $fine = $validator->validate(namedFlow(str_repeat('a', 60)));
    app(CurrentOrganization::class)->forget();

    expect($long['valid'])->toBeFalse()
        ->and(collect($long['errors'])->pluck('node')->all())->toContain('q_size')
        ->and($array['valid'])->toBeFalse()
        ->and($fine['valid'])->toBeTrue();
});
