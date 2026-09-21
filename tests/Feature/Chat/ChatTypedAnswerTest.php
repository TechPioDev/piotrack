<?php

declare(strict_types=1);

/**
 * The chat window keeps its message box open on every step, as visitors
 * expect from any messenger. A visitor who types where buttons were offered
 * gets the answer they plainly named - and a clear "tap one of the options"
 * when what they typed could mean any of them, or none.
 */

use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Support\CurrentOrganization;

function typedAnswerFlow(): array
{
    return [
        'start' => 'topic',
        'nodes' => [
            'topic' => [
                'type' => 'choice', 'field' => 'topic', 'text' => 'What would you like to explore?',
                'options' => [
                    ['id' => 'features', 'label' => 'Explore features', 'next' => 'done'],
                    ['id' => 'tickets', 'label' => 'Ticket management', 'next' => 'done'],
                    ['id' => 'technicians', 'label' => 'Technician management', 'next' => 'done'],
                    ['id' => 'pricing', 'label' => 'Pricing', 'next' => 'done', 'score' => 20],
                    ['id' => 'demo', 'label' => 'Book a demo', 'next' => 'done'],
                ],
            ],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
        ],
    ];
}

beforeEach(function () {
    [$this->org] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active', 'flow' => typedAnswerFlow()]);
    app(CurrentOrganization::class)->forget();

    $this->key = $this->widget->public_key;
});

function typeInto($test, string $value)
{
    $token = $test->postJson("/wc/{$test->key}/conversations")->assertOk()->json('token');

    return [$token, $test->postJson("/wc/{$test->key}/conversations/{$token}/messages", ['value' => $value])];
}

it('takes a typed answer that names an option, whatever its case or punctuation', function (string $typed, string $picked) {
    [$token, $reply] = typeInto($this, $typed);

    $reply->assertOk()->assertJsonPath('done', true);
    expect(ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token)->answers['topic'])->toBe($picked);
})->with([
    'the label' => ['Pricing', 'pricing'],
    'in other case' => ['PRICING!', 'pricing'],
    'a unique part of a label' => ['demo', 'demo'],
    'the label inside a sentence' => ['i want pricing please', 'pricing'],
    'a unique word' => ['tickets', 'tickets'],
]);

it('scores and records a typed answer exactly as if the button were tapped', function () {
    [$token] = typeInto($this, 'pricing');

    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token);
    expect($conversation->lead_score)->toBe(20)
        ->and($conversation->messages()->where('role', 'visitor')->pluck('body')->all())->toBe(['Pricing']);
});

it('asks for a tap when the typed reply could mean several options, or none', function (string $typed) {
    [$token, $reply] = typeInto($this, $typed);

    $reply->assertStatus(422)->assertJsonPath('errors.option.0', 'Please tap one of the options above.');

    // Nothing was said and nothing moved: the visitor is still on the question.
    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token);
    expect($conversation->messages()->where('role', 'visitor')->count())->toBe(0)
        ->and($conversation->answers['_node'])->toBe('topic');
})->with([
    'several options' => ['management'],
    'no option at all' => ['yes'],
    'too short to tell' => ['te'],
]);

it('lets a visitor accept the privacy question by typing its answer', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['consent' => ['required' => true]]);
    app(CurrentOrganization::class)->forget();

    [$token, $reply] = typeInto($this, 'Accept');

    $reply->assertOk()->assertJsonPath('node.id', 'topic');
    expect(ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token)->answers['_consent'])->toBeTrue();
});
