<?php

declare(strict_types=1);

/**
 * The template gallery (CHAT-055, CHAT-057): ready-made conversations by type
 * of business. A template is the first thing a new customer's visitors see,
 * so each one is held to more than "valid": it must run from start to finish
 * through the real engine, ask for an email, and belong to a business type.
 */

use App\Models\ChatWidget;
use App\Services\Chat\ChatFlowTemplates;
use App\Services\Chat\ChatFlowValidator;
use App\Services\Chat\DefaultChatFlow;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');
});

it('files every template under a type of business, with a preview of its conversation', function () {
    $catalog = app(ChatFlowTemplates::class)->catalog();

    expect(count($catalog))->toBeGreaterThanOrEqual(16);
    foreach ($catalog as $template) {
        expect($template['category'])->toBeIn(ChatFlowTemplates::CATEGORIES)
            ->and($template['flow']['nodes'])->toHaveCount($template['steps'])
            ->and(trim($template['description']))->not->toBe('');
    }

    // Every business type offers at least one template.
    expect(array_unique(array_column($catalog, 'category')))->toEqualCanonicalizing(ChatFlowTemplates::CATEGORIES);
});

it('ships only templates that are valid with nothing to warn about', function () {
    $validator = app(ChatFlowValidator::class);

    foreach (app(ChatFlowTemplates::class)->catalog() as $template) {
        $result = $validator->validate($template['flow']);
        expect($result['errors'])->toBe([], "{$template['key']} has errors")
            ->and($result['warnings'])->toBe([], "{$template['key']} has warnings");
    }
});

it('asks for a required email in every template, so a chat can become a lead', function () {
    foreach (app(ChatFlowTemplates::class)->catalog() as $template) {
        $emails = array_filter($template['flow']['nodes'], fn (array $n) => ($n['type'] ?? '') === 'input' && ($n['field'] ?? '') === 'email');
        expect($emails)->not->toBeEmpty("{$template['key']} never asks for an email");
        foreach ($emails as $email) {
            expect($email['optional'] ?? false)->toBeFalse("{$template['key']} makes email optional");
        }
    }
});

it('runs every template from start to finish through the real chat engine', function () {
    app(CurrentOrganization::class)->set($this->org);
    $widget = ChatWidget::create(['name' => 'Gallery test', 'status' => 'draft']);
    app(CurrentOrganization::class)->forget();
    $this->actingAs($this->owner);

    $sample = ['email' => 'dana@client.test', 'phone' => '215-555-0101', 'number' => '12'];

    foreach (app(ChatFlowTemplates::class)->catalog() as $template) {
        $flow = $template['flow'];
        $reply = $this->postJson(route('chat.flow.test', $widget), ['flow' => $flow])->assertOk();
        $token = $reply->json('token');

        // A visitor who takes the first answer each time and fills in every box.
        for ($turn = 0; $turn < 40 && ! $reply->json('done'); $turn++) {
            $node = $reply->json('node');
            $answer = in_array($node['type'], ['choice', 'consent'], true)
                ? ['option' => $node['options'][0]['id']]
                : ['value' => $sample[$node['input'] ?? 'text'] ?? 'Test answer'];
            $reply = $this->postJson(route('chat.flow.test', $widget), ['flow' => $flow, 'token' => $token, ...$answer])->assertOk();
        }

        expect($reply->json('done'))->toBeTrue("{$template['key']} did not finish");
    }
});

it('starts a new widget with the conversation for the business type chosen', function () {
    $this->actingAs($this->owner)
        ->post(route('chat.widgets.store'), ['name' => 'Clinic website', 'template' => 'healthcare'])
        ->assertSessionHasNoErrors();

    $widget = ChatWidget::withoutGlobalScope('tenant')->firstWhere('name', 'Clinic website');
    expect($widget->flow)->toBe(app(ChatFlowTemplates::class)->flow('healthcare'))
        ->and($widget->status)->toBe('draft');
});

it('refuses a template that does not exist, and keeps the standard one when none is chosen', function () {
    $this->actingAs($this->owner)
        ->post(route('chat.widgets.store'), ['name' => 'Typo', 'template' => 'nope'])
        ->assertSessionHasErrors(['template' => 'That template no longer exists. Choose another, or start from the standard conversation.']);

    $this->actingAs($this->owner)
        ->post(route('chat.widgets.store'), ['name' => 'Plain'])
        ->assertSessionHasNoErrors();

    expect(ChatWidget::withoutGlobalScope('tenant')->firstWhere('name', 'Plain')->flow)->toBe(DefaultChatFlow::definition());
});

it('offers the gallery to the builder and the new-widget form', function () {
    app(CurrentOrganization::class)->set($this->org);
    $widget = ChatWidget::create(['name' => 'Builder', 'status' => 'draft']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->get(route('chat.flow.edit', $widget))
        ->assertInertia(fn ($page) => $page
            ->has('templates.0.flow.nodes')
            ->where('templates.0.category', 'IT & managed services'));

    $this->actingAs($this->owner)->get(route('chat.widgets.index'))
        ->assertInertia(fn ($page) => $page
            ->where('templates.0.key', 'msp_qualification')
            ->missing('templates.0.flow'));
});
