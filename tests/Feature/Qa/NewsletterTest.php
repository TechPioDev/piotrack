<?php

declare(strict_types=1);

/**
 * Homepage newsletter signup: a real platform-level list. Idempotent on
 * duplicates, honeypot-guarded, validated - and never tenant-stamped.
 */

use App\Models\AuditLog;
use App\Models\NewsletterSubscriber;

it('subscribes an email once, idempotently, and audits at platform level', function () {
    $this->post(route('newsletter.subscribe'), ['email' => 'Owner@MSP.test'])
        ->assertRedirect()->assertSessionHas('status');
    $this->post(route('newsletter.subscribe'), ['email' => 'owner@msp.test'])
        ->assertRedirect()->assertSessionHas('status'); // duplicate is a warm yes, not an error

    expect(NewsletterSubscriber::count())->toBe(1)
        ->and(NewsletterSubscriber::first()->email)->toBe('owner@msp.test');

    $audit = AuditLog::where('action', 'newsletter.subscribed')->get();
    expect($audit)->toHaveCount(1)
        ->and($audit[0]->organization_id)->toBeNull();
});

it('rejects invalid emails and quietly absorbs honeypot submissions', function () {
    $this->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    $this->post(route('newsletter.subscribe'), ['email' => 'bot@spam.test', 'website' => 'http://spam'])
        ->assertRedirect()->assertSessionHas('status');

    expect(NewsletterSubscriber::count())->toBe(0);
});
