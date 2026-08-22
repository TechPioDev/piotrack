<?php

declare(strict_types=1);

/**
 * Website Chat Phase 2 — the flow builder.
 *
 * Covers the promises the builder makes: a broken conversation cannot be
 * published, templates are real working flows, the new action/branching steps
 * execute, and a preview runs the real engine without ever touching the CRM.
 */

use App\Authorization\Role;
use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\Chat\ChatFlowTemplates;
use App\Services\Chat\ChatFlowValidator;
use App\Services\Chat\DefaultChatFlow;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'Builder widget', 'status' => 'draft']);
    app(CurrentOrganization::class)->forget();
});

// ---------------------------------------------------------------- validation

it('accepts a well-formed conversation', function () {
    $result = app(ChatFlowValidator::class)->validate([
        'start' => 'hello',
        'nodes' => [
            'hello' => ['type' => 'message', 'text' => 'Hi there', 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
        ],
    ]);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty()
        ->and($result['warnings'])->toBeEmpty();
});

it('reports every way a conversation can strand a visitor', function () {
    $validator = app(ChatFlowValidator::class);

    // A step that points nowhere.
    expect($validator->validate([
        'start' => 'hello',
        'nodes' => ['hello' => ['type' => 'message', 'text' => 'Hi', 'next' => null]],
    ])['valid'])->toBeFalse();

    // A step that points at something deleted.
    expect($validator->validate([
        'start' => 'hello',
        'nodes' => ['hello' => ['type' => 'message', 'text' => 'Hi', 'next' => 'ghost']],
    ])['valid'])->toBeFalse();

    // A question with no answers.
    expect($validator->validate([
        'start' => 'q',
        'nodes' => ['q' => ['type' => 'choice', 'text' => 'Pick', 'options' => []]],
    ])['valid'])->toBeFalse();

    // No start node at all.
    expect($validator->validate([
        'start' => null,
        'nodes' => ['done' => ['type' => 'end', 'text' => 'Bye']],
    ])['valid'])->toBeFalse();

    // An empty conversation.
    expect($validator->validate(['start' => null, 'nodes' => []])['valid'])->toBeFalse();
});

it('warns about steps a visitor can never reach', function () {
    $result = app(ChatFlowValidator::class)->validate([
        'start' => 'hello',
        'nodes' => [
            'hello' => ['type' => 'message', 'text' => 'Hi', 'next' => 'done'],
            'done' => ['type' => 'end', 'text' => 'Bye'],
            'orphan' => ['type' => 'message', 'text' => 'Nobody sees me', 'next' => 'done'],
        ],
    ]);

    expect($result['valid'])->toBeTrue()
        ->and($result['warnings'])->toHaveCount(1)
        ->and($result['warnings'][0]['node'])->toBe('orphan');
});

it('warns when a question saves its answer nowhere', function () {
    $result = app(ChatFlowValidator::class)->validate([
        'start' => 'q',
        'nodes' => [
            'q' => [
                'type' => 'choice', 'text' => 'What do you need?',
                'options' => [['id' => 'a', 'label' => 'Managed IT', 'next' => 'done']],
            ],
            'done' => ['type' => 'end', 'text' => 'Thanks'],
        ],
    ]);

    // It still routes the visitor, so this blocks nothing - but the answer is
    // lost to the CRM, the inbox and any later condition, so it is called out.
    expect($result['valid'])->toBeTrue()
        ->and(collect($result['warnings'])->pluck('message')->implode(' '))
        ->toContain('does not save the answer anywhere');
});

it('ships a default conversation that stores every answer it asks for', function () {
    $flow = DefaultChatFlow::definition();

    foreach ($flow['nodes'] as $id => $node) {
        if (($node['type'] ?? null) === 'choice') {
            expect(trim((string) ($node['field'] ?? '')))->not->toBe('', "choice step {$id} must store its answer");
        }
    }
});

// ----------------------------------------------------------------- templates

it('ships templates that are all valid conversations', function () {
    $templates = app(ChatFlowTemplates::class);
    $validator = app(ChatFlowValidator::class);

    expect($templates->catalog())->not->toBeEmpty();

    foreach ($templates->catalog() as $entry) {
        $flow = $templates->flow($entry['key']);
        expect($flow)->not->toBeNull();

        $result = $validator->validate($flow);
        expect($result['valid'])->toBeTrue("template {$entry['key']} should be valid: ".json_encode($result['errors']));
    }
});

// ------------------------------------------------------------ saving/publish

it('saves a draft but refuses to publish a broken conversation', function () {
    $this->actingAs($this->owner);

    $broken = ['start' => 'hello', 'nodes' => ['hello' => ['type' => 'message', 'text' => 'Hi', 'next' => 'ghost']]];

    // Saving a draft is always allowed — work in progress is normal.
    $this->put(route('chat.flow.update', $this->widget), ['flow' => $broken])
        ->assertSessionHasNoErrors();

    // Publishing it is not.
    $this->put(route('chat.flow.update', $this->widget), ['flow' => $broken, 'publish' => true])
        ->assertSessionHasErrors('flow');

    expect($this->widget->fresh()->status)->toBe('draft');
});

it('publishes a valid conversation and takes the widget live', function () {
    $this->actingAs($this->owner);

    $flow = [
        'start' => 'hello',
        'nodes' => [
            'hello' => ['type' => 'message', 'text' => 'Hi there', 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
        ],
    ];

    $this->put(route('chat.flow.update', $this->widget), ['flow' => $flow, 'publish' => true])
        ->assertSessionHasNoErrors();

    expect($this->widget->fresh()->status)->toBe('active');
});

it('applies a template to the widget', function () {
    $this->actingAs($this->owner);

    $this->post(route('chat.flow.template', $this->widget), ['template' => 'cmmc'])
        ->assertSessionHasNoErrors();

    $flow = $this->widget->fresh()->flow;
    expect($flow['nodes'])->toHaveKey('q_level');
});

// ------------------------------------------------------------- new node types

it('branches on an earlier answer with a condition step', function () {
    $this->actingAs($this->owner);

    $flow = [
        'start' => 'q_size',
        'nodes' => [
            'q_size' => [
                'type' => 'choice', 'text' => 'How many employees?', 'field' => 'company_size',
                'options' => [
                    ['id' => 'big', 'label' => '250+', 'score' => 0, 'next' => 'check'],
                    ['id' => 'small', 'label' => '1-10', 'score' => 0, 'next' => 'check'],
                ],
            ],
            'check' => ['type' => 'condition', 'field' => 'company_size', 'operator' => 'equals', 'value' => 'big', 'next' => 'end_enterprise', 'otherwise' => 'end_smb'],
            'end_enterprise' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Enterprise route'],
            'end_smb' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Small business route'],
        ],
    ];

    // Enterprise branch.
    $start = $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow]);
    $token = $start->json('token');
    $big = $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow, 'token' => $token, 'option' => 'big']);
    expect(collect($big->json('messages'))->pluck('body')->implode(' '))->toContain('Enterprise route');

    // Small-business branch.
    $start2 = $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow]);
    $small = $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow, 'token' => $start2->json('token'), 'option' => 'small']);
    expect(collect($small->json('messages'))->pluck('body')->implode(' '))->toContain('Small business route');
});

it('adds points with a score step and records a tag', function () {
    $this->actingAs($this->owner);

    $flow = [
        'start' => 'boost',
        'nodes' => [
            'boost' => ['type' => 'score', 'points' => 42, 'next' => 'label'],
            'label' => ['type' => 'tag', 'tag' => 'high-intent', 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Done'],
        ],
    ];

    $response = $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow]);
    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $response->json('token'));

    expect($conversation->lead_score)->toBe(42)
        ->and($conversation->answers['_tags'])->toContain('high-intent');
});

// -------------------------------------------------------------- preview mode

it('runs a preview through the real engine without creating CRM records', function () {
    $this->actingAs($this->owner);

    $flow = [
        'start' => 'in_email',
        'nodes' => [
            'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'Your email?', 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
        ],
    ];

    $start = $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow]);
    $this->postJson(route('chat.flow.test', $this->widget), [
        'flow' => $flow,
        'token' => $start->json('token'),
        'value' => 'preview@example.test',
    ])->assertOk()->assertJsonPath('done', true);

    // The conversation exists, flagged as a preview...
    $conversation = ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $start->json('token'));
    expect($conversation->is_preview)->toBeTrue();

    // ...but nothing reached the CRM.
    expect(Contact::withoutGlobalScope('tenant')->where('email', 'preview@example.test')->count())->toBe(0)
        ->and(Lead::withoutGlobalScope('tenant')->count())->toBe(0);
});

it('keeps previews out of the conversations inbox', function () {
    $this->actingAs($this->owner);

    $flow = [
        'start' => 'hello',
        'nodes' => [
            'hello' => ['type' => 'message', 'text' => 'Hi', 'next' => 'done'],
            'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Bye'],
        ],
    ];
    $this->postJson(route('chat.flow.test', $this->widget), ['flow' => $flow]);

    $response = $this->get(route('chat.inbox'));

    $response->assertOk();
    expect($response->viewData('page')['props']['conversations'])->toBeEmpty();
});

// ------------------------------------------------------------- authorization

it('restricts the builder to users who can manage widgets', function () {
    $member = addMember($this->org, Role::Viewer);

    $this->actingAs($member)
        ->get(route('chat.flow.edit', $this->widget))
        ->assertForbidden();

    $this->actingAs($member)
        ->put(route('chat.flow.update', $this->widget), ['flow' => ['start' => 'a', 'nodes' => ['a' => ['type' => 'end', 'text' => 'x']]]])
        ->assertForbidden();
});

it('never lets one tenant edit another tenant widget flow', function () {
    [$otherOrg, $otherOwner] = makeOrganization('Rival MSP');
    subscribeOrganization($otherOrg, 'enterprise');

    $this->actingAs($otherOwner)
        ->get(route('chat.flow.edit', $this->widget))
        ->assertNotFound();
});
