<?php

declare(strict_types=1);

/**
 * Alerts & Notifications (Module 04). Notifications are asserted as database
 * rows (the queue runs sync in tests), because the sweep's per-day dedupe
 * reads those same rows — faking the channel would blind the thing under test.
 */

use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Models\AiVisibilityCheck;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\Keyword;
use App\Models\NotificationPreference;
use App\Models\ScoringRule;
use App\Notifications\SqlPromotedNotification;
use App\Services\AlertSweep;
use App\Services\Sales\BookingService;
use App\Services\Sales\LeadScoringService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Alerts Org');
    subscribeOrganization($this->org, 'growth');
});

function dbNotifications($user, ?string $needle = null)
{
    $q = $user->notifications();
    if ($needle !== null) {
        $q->where('data->title', 'like', "%{$needle}%");
    }

    return $q->get();
}

it('notifies when a contact is promoted to SQL, exactly once', function () {
    app(CurrentOrganization::class)->set($this->org);
    ScoringRule::create(['name' => 'CFO', 'category' => 'demographic', 'attribute' => 'title', 'operator' => 'contains', 'value' => 'CFO', 'points' => 60, 'is_active' => true]);
    $contact = Contact::create(['first_name' => 'Michael', 'email' => 'm@p.test', 'title' => 'CFO', 'lifecycle_stage' => 'lead']);

    $scoring = app(LeadScoringService::class);
    $scoring->apply($contact);
    $scoring->apply($contact->refresh()); // recompute must not repeat the alert
    app(CurrentOrganization::class)->forget();

    $rows = dbNotifications($this->owner, 'sales-qualified');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->data['category'])->toBe('sales')
        ->and($rows[0]->data['body'])->toContain('Michael')
        ->and($rows[0]->data['url'])->toContain('/crm/contacts/');
});

it('notifies the seller when a meeting is booked', function () {
    app(CurrentOrganization::class)->set($this->org);
    $page = BookingPage::create(['name' => 'Intro call', 'slug' => 'alerts-intro-'.uniqid(), 'meeting_type' => 'intro call', 'duration_minutes' => 30, 'status' => 'published', 'owner_id' => $this->owner->id]);
    app(BookingService::class)->book($page, [
        'name' => 'Dana Prospect',
        'email' => 'dana@prospect.test',
        'scheduled_at' => now()->addDays(2)->setTime(14, 0),
    ]);
    app(CurrentOrganization::class)->forget();

    $rows = dbNotifications($this->owner, 'meeting booked');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->data['body'])->toContain('Dana Prospect');
});

it('warns owners at 80% of a metered limit, deduped per day', function () {
    // Growth allows 25,000 emails; 20,000 used is exactly the threshold.
    app(UsageMeter::class)->increment($this->org, Limit::Emails, 20000);

    app(CurrentOrganization::class)->set($this->org);
    $sweep = app(AlertSweep::class);
    $first = $sweep->run($this->org);
    $second = $sweep->run($this->org);
    app(CurrentOrganization::class)->forget();

    expect($first['usage'])->toBe(1)
        ->and($second['usage'])->toBe(0); // same day, same key -> no repeat

    $rows = dbNotifications($this->owner, 'limit approaching');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->data['category'])->toBe('billing')
        ->and($rows[0]->data['key'])->toBe('usage:emails');
});

it('stays quiet below the usage threshold', function () {
    app(UsageMeter::class)->increment($this->org, Limit::Emails, 10000);

    app(CurrentOrganization::class)->set($this->org);
    $result = app(AlertSweep::class)->run($this->org);
    app(CurrentOrganization::class)->forget();

    expect($result['usage'])->toBe(0);
});

it('alerts a tracked keyword falling five or more positions, not smaller wobbles', function () {
    app(CurrentOrganization::class)->set($this->org);
    $dropped = Keyword::create(['phrase' => 'managed it services philadelphia', 'is_tracked' => true]);
    $dropped->rankings()->create(['engine' => 'google', 'position' => 8, 'checked_at' => now()->subDays(2)]);
    $dropped->rankings()->create(['engine' => 'google', 'position' => 15, 'checked_at' => now()->subDay()]);

    $wobble = Keyword::create(['phrase' => 'msp near me', 'is_tracked' => true]);
    $wobble->rankings()->create(['engine' => 'google', 'position' => 8, 'checked_at' => now()->subDays(2)]);
    $wobble->rankings()->create(['engine' => 'google', 'position' => 10, 'checked_at' => now()->subDay()]);

    $sweep = app(AlertSweep::class);
    $first = $sweep->run($this->org);
    $second = $sweep->run($this->org);
    app(CurrentOrganization::class)->forget();

    expect($first['rankings'])->toBe(1)
        ->and($second['rankings'])->toBe(0);

    $rows = dbNotifications($this->owner, 'ranking dropped');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->data['body'])->toContain('position 8 to 15')
        ->and($rows[0]->data['category'])->toBe('marketing');
});

it('alerts a sharp AI-visibility swing between windows', function () {
    app(CurrentOrganization::class)->set($this->org);
    // Previous window: never mentioned. Current window: always mentioned.
    foreach ([10, 9] as $daysAgo) {
        AiVisibilityCheck::create(['prompt' => 'best msp', 'engine' => 'chatgpt', 'brand' => 'Us', 'mentioned' => false, 'checked_at' => now()->subDays($daysAgo)]);
    }
    foreach ([2, 1] as $daysAgo) {
        AiVisibilityCheck::create(['prompt' => 'best msp', 'engine' => 'chatgpt', 'brand' => 'Us', 'mentioned' => true, 'checked_at' => now()->subDays($daysAgo)]);
    }

    $result = app(AlertSweep::class)->run($this->org);
    app(CurrentOrganization::class)->forget();

    expect($result['ai_visibility'])->toBe(1);
    $rows = dbNotifications($this->owner, 'AI visibility');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->data['body'])->toContain('+100');
});

it('respects a disabled email preference while keeping the in-app channel', function () {
    NotificationPreference::create([
        'user_id' => $this->owner->id,
        'category' => 'sales',
        'channel' => 'email',
        'enabled' => false,
    ]);
    $this->owner->load('notificationPreferences');

    $channels = (new SqlPromotedNotification(1, 'Test', 60))->via($this->owner->refresh());

    expect($channels)->toBe(['database']);
});

it('offers the new categories in the preference matrix', function () {
    $props = $this->actingAs($this->owner)->get(route('notifications.index'))->assertOk()
        ->viewData('page')['props'];

    expect($props['categories'])->toContain('sales', 'marketing');
});
