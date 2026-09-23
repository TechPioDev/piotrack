<?php

declare(strict_types=1);

/**
 * The AI step answers from what this business has published, not from what a
 * model happens to believe about IT companies.
 *
 * Its facts come from the tenant's own rows - published content pieces, published
 * website pages, their service lines - and from the notes an owner writes on the
 * widget for the things that live nowhere else. Nothing is crawled and nothing
 * belonging to another tenant is ever reachable.
 */

use App\Ai\AiCompletion;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use App\Models\ContentPiece;
use App\Models\PageSection;
use App\Models\SitePage;
use App\Services\Ai\AiGateway;
use App\Services\Chat\ChatKnowledge;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'Acme website', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();
});

function publishPost(string $title, string $body, string $status = 'published'): ContentPiece
{
    return ContentPiece::create([
        'title' => $title,
        'slug' => str($title)->slug()->value(),
        'content_type' => 'blog',
        'format' => 'markdown',
        'status' => $status,
        'excerpt' => '',
        'body' => $body,
    ]);
}

it('answers from the tenant’s own published pages and posts', function () {
    app(CurrentOrganization::class)->set($this->org);
    publishPost('Microsoft 365 migration', 'We migrate Microsoft 365 tenants in evenings and weekends, with no downtime for staff.');
    publishPost('Old draft about pricing', 'Draft pricing nobody approved.', 'draft');

    $found = app(ChatKnowledge::class)->forQuestion('Do you handle Microsoft 365 migration?', $this->widget);
    app(CurrentOrganization::class)->forget();

    expect($found['sources'])->toBe(['Microsoft 365 migration']);
    expect($found['text'])->toContain('evenings and weekends');
    // A draft is not published, so the assistant must not have it.
    expect($found['text'])->not->toContain('Draft pricing');
});

it('reads a published website page, headline and sections alike', function () {
    app(CurrentOrganization::class)->set($this->org);
    $page = SitePage::create([
        'type' => 'service',
        'slug' => 'cyber-security',
        'title' => 'Cyber security for small firms',
        'headline' => 'Cyber Essentials in six weeks',
        'status' => SitePage::STATUS_PUBLISHED,
    ]);
    PageSection::create([
        'site_page_id' => $page->id,
        'type' => 'text',
        'heading' => 'What is included',
        'body' => '<p>Monitoring, patching and a Cyber Essentials assessment.</p>',
        'is_visible' => true,
    ]);

    $found = app(ChatKnowledge::class)->forQuestion('Can you help with cyber security?', $this->widget);
    app(CurrentOrganization::class)->forget();

    expect($found['sources'])->toContain('Cyber security for small firms');
    expect($found['text'])->toContain('Cyber Essentials')
        ->and($found['text'])->toContain('Monitoring, patching')
        // Markup from the page never reaches the model.
        ->and($found['text'])->not->toContain('<p>');
});

it('puts the owner’s own notes first, since they live nowhere else', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->forceFill(['settings' => ['knowledge' => 'We cover Reading and Slough only. Managed clients get a same-day response.']])->save();

    $found = app(ChatKnowledge::class)->forQuestion('Do you cover Reading?', $this->widget);
    app(CurrentOrganization::class)->forget();

    expect($found['text'])->toStartWith('What the team says about this business:');
    expect($found['text'])->toContain('Reading and Slough');
});

it('never reaches another tenant’s content', function () {
    [$other] = makeOrganization('Someone Else Ltd');
    app(CurrentOrganization::class)->set($other);
    publishPost('Microsoft 365 migration', 'Their secret migration method.');
    app(CurrentOrganization::class)->forget();

    app(CurrentOrganization::class)->set($this->org);
    $found = app(ChatKnowledge::class)->forQuestion('Do you handle Microsoft 365 migration?', $this->widget);
    app(CurrentOrganization::class)->forget();

    expect($found['sources'])->toBe([]);
    expect($found['text'])->not->toContain('secret migration');
});

it('says plainly that it has nothing, rather than inventing something', function () {
    app(CurrentOrganization::class)->set($this->org);
    $found = app(ChatKnowledge::class)->forQuestion('What is your policy on carrier pigeons?', $this->widget);
    app(CurrentOrganization::class)->forget();

    expect($found['sources'])->toBe([]);
});

it('hands those facts to the model, and keeps what it used with the answer', function () {
    app(CurrentOrganization::class)->set($this->org);
    publishPost('Backup and recovery', 'Backups run hourly and are tested every quarter.');
    $this->widget->forceFill([
        'settings' => ['knowledge' => 'We never charge for the first hour of an incident.'],
        'flow' => [
            'start' => 'ask',
            'nodes' => [
                'ask' => ['type' => 'ai', 'text' => 'What would you like to know?', 'next' => 'done'],
                'done' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks!'],
            ],
        ],
    ])->save();
    app(CurrentOrganization::class)->forget();

    $seen = null;
    $gateway = Mockery::mock(AiGateway::class);
    $gateway->shouldReceive('run')->once()
        ->andReturnUsing(function (string $feature, string $prompt, array $variables) use (&$seen) {
            $seen = $variables;

            return new AiCompletion('Backups run hourly and are tested each quarter.', 10, 10, 'test-model');
        });
    app()->instance(AiGateway::class, $gateway);

    $key = $this->widget->public_key;
    $token = $this->postJson("/wc/{$key}/conversations", ['visitor' => 'kb-1'])->assertOk()->json('token');
    $this->postJson("/wc/{$key}/conversations/{$token}/messages", ['value' => 'How often do backups run?'])->assertOk();

    expect($seen['knowledge'])->toContain('Backups run hourly')
        ->and($seen['knowledge'])->toContain('never charge for the first hour');

    $message = ChatMessage::withoutGlobalScopes()
        ->where('role', 'bot')
        ->whereNotNull('meta')
        ->get()
        ->first(fn ($m) => isset($m->meta['sources']));

    expect($message)->not->toBeNull();
    expect($message->meta['sources'])->toBe(['Backup and recovery']);
});
