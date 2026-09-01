<?php

declare(strict_types=1);

/**
 * QA §19/§20 - lead generation and scoring.
 *
 * §19's chain is driven from where it actually starts: an unauthenticated
 * visitor posting the public form at /f/{slug}. Everything downstream - lead,
 * contact, source, campaign, list membership, owner notification, workflow
 * enrolment, audit entry - is then asserted as linked records.
 *
 * §20 specifies a 95-point model. The engine supports demographic rules on six
 * attributes plus an aggregate intent_score fed by weighted behavioural
 * signals. Firmographic inputs (company size, industry, region) are not
 * available, so the "+15 for company size 100-250" line cannot be expressed and
 * the reachable total is 80 rather than 95. That is asserted here explicitly
 * rather than quietly reworked, so the gap stays visible.
 *
 * Prospect: Michael Rodriguez, CFO, Precision Manufacturing Group.
 */

use App\Models\AlertRule;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Form;
use App\Models\MarketingList;
use App\Models\SalesAlert;
use App\Models\ScoringRule;
use App\Models\Workflow;
use App\Notifications\LeadCapturedNotification;
use App\Services\Sales\AlertService;
use App\Services\Sales\IntentService;
use App\Services\Sales\LeadScoringService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Notification;

const PROSPECT_EMAIL = 'michael.rodriguez@precisionmfg-test.com';

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);

    $this->list = MarketingList::create(['name' => 'CMMC nurture', 'type' => 'static']);

    $this->form = Form::create([
        'name' => 'Philadelphia CMMC Lead Generation',
        'slug' => 'philadelphia-cmmc',
        'fields' => [
            ['name' => 'first_name', 'type' => 'text', 'required' => true],
            ['name' => 'last_name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'required' => true],
        ],
        'target_list_id' => $this->list->id,
        'lifecycle_stage' => 'lead',
        'status' => 'published',
    ]);

    app(CurrentOrganization::class)->forget();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('carries an anonymous visitor through to a linked, enrolled, notified lead', function () {
    Notification::fake();

    // A nurture workflow waiting on form submissions.
    app(CurrentOrganization::class)->set($this->org);
    $workflow = Workflow::create([
        'name' => 'CMMC nurture sequence',
        'trigger_type' => 'form_submission',
        'status' => 'active',
    ]);
    app(CurrentOrganization::class)->forget();

    // -- The visitor: unauthenticated, no tenant context ------------------
    expect(app(CurrentOrganization::class)->isSet())->toBeFalse();

    $this->post('/f/philadelphia-cmmc', [
        'first_name' => 'Michael',
        'last_name' => 'Rodriguez',
        'email' => PROSPECT_EMAIL,
    ])->assertSuccessful();

    // -- Everything the chain should have produced ------------------------
    app(CurrentOrganization::class)->set($this->org);

    $contact = Contact::where('email', PROSPECT_EMAIL)->first();

    expect($contact)->not->toBeNull('the public submission created no contact')
        ->and($contact->organization_id)->toBe($this->org->id)
        ->and($contact->lead_source)->toBe('form')
        ->and($contact->lifecycle_stage)->toBe('lead');

    // Submission recorded and counted.
    expect(DB::table('form_submissions')->where('contact_id', $contact->id)->count())->toBe(1)
        ->and($this->form->fresh()->submission_count)->toBe(1);

    // Enrolled on the form's target list.
    expect($this->list->fresh()->contacts()->pluck('contacts.id')->all())->toContain($contact->id);

    // Nurture workflow started.
    expect(DB::table('workflow_enrollments')->where('workflow_id', $workflow->id)->count())
        ->toBe(1, 'the nurture workflow did not enrol the new lead');

    // Audit trail carries the capture.
    expect(DB::table('audit_logs')->where('action', 'lead.captured')->count())->toBe(1);

    app(CurrentOrganization::class)->forget();

    // Owner notified.
    Notification::assertSentTo($this->owner, LeadCapturedNotification::class);
});

it('rejects a submission that fails the form rules and creates nothing', function () {
    $this->post('/f/philadelphia-cmmc', [
        'first_name' => 'Michael',
        'email' => 'not-an-email',
    ])->assertSessionHasErrors();

    app(CurrentOrganization::class)->set($this->org);
    expect(Contact::count())->toBe(0)
        ->and($this->form->fresh()->submission_count)->toBe(0);
    app(CurrentOrganization::class)->forget();
});

it('silently absorbs a bot that fills the honeypot', function () {
    $this->post('/f/philadelphia-cmmc', [
        'first_name' => 'Bot', 'last_name' => 'Net',
        'email' => 'bot@spam-test.com',
        'website' => 'http://spam.example',
    ])->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);
    expect(Contact::count())->toBe(0, 'the honeypot let a bot through');
    app(CurrentOrganization::class)->forget();
});

/*
|--------------------------------------------------------------------------
| §20 - scoring arithmetic and the Hot threshold
|--------------------------------------------------------------------------
*/

it('computes §20 scoring exactly, and reaches 80 rather than 95', function () {
    app(CurrentOrganization::class)->set($this->org);

    $contact = Contact::create([
        'first_name' => 'Michael', 'last_name' => 'Rodriguez',
        'email' => PROSPECT_EMAIL, 'title' => 'CFO', 'lead_source' => 'paid',
    ]);

    // Behavioural signals, weighted as §20 specifies.
    $intent = app(IntentService::class);
    $signals = [
        'pricing_page_view' => 20,
        'cybersecurity_page_view' => 10,
        'ebook_download' => 10,
        'repeat_visit' => 10,
        'email_click' => 10,
    ];
    foreach ($signals as $type => $weight) {
        $intent->record($contact, $type, $weight);
    }

    expect($intent->intentScore($contact))->toBe(60, 'weighted intent signals did not sum');

    // The two rules the engine can actually express.
    ScoringRule::create([
        'name' => 'CFO title', 'category' => 'demographic', 'attribute' => 'title',
        'operator' => 'contains', 'value' => 'CFO', 'points' => 20, 'is_active' => true,
    ]);
    ScoringRule::create([
        'name' => 'High behavioural intent', 'category' => 'behavioural', 'attribute' => 'intent_score',
        'operator' => 'gte', 'value' => '60', 'points' => 60, 'is_active' => true,
    ]);

    $scoring = app(LeadScoringService::class);
    $scored = $scoring->apply($contact->fresh());

    // 20 (CFO) + 60 (intent). §20's +15 for company size 100-250 has no
    // firmographic attribute to hang on, so 95 is not reachable.
    expect($scored->lead_score)->toBe(80)
        ->and($scoring->temperature($scored->lead_score))->toBe('hot')
        ->and($scored->lifecycle_stage)->toBe('sql');

    app(CurrentOrganization::class)->forget();
});

it('fires a sales alert once the score crosses the threshold', function () {
    app(CurrentOrganization::class)->set($this->org);

    AlertRule::create([
        'name' => 'Hot lead', 'trigger' => 'score_threshold',
        'threshold' => 80, 'channel' => 'in_app', 'is_active' => true,
    ]);

    $contact = Contact::create([
        'first_name' => 'Michael', 'last_name' => 'Rodriguez',
        'email' => PROSPECT_EMAIL, 'title' => 'CFO',
    ]);

    $alerts = app(AlertService::class);

    // Below the threshold: silence.
    $contact->update(['lead_score' => 55]);
    expect($alerts->evaluate($contact->fresh()))->toBe(0)
        ->and(SalesAlert::count())->toBe(0);

    // At the threshold: it fires.
    $contact->update(['lead_score' => 80]);
    expect($alerts->evaluate($contact->fresh()))->toBe(1)
        ->and(SalesAlert::count())->toBe(1);

    app(CurrentOrganization::class)->forget();
});

it('scores firmographic attributes from the contact company record', function () {
    // The old §20 gap pin inverted: company size/city/region/industry are now
    // scoreable, sourced from first-party CRM data (LSCR-011/012). §20's
    // "+15 for company size 100-250" is finally expressible — and 95 reachable.
    app(CurrentOrganization::class)->set($this->org);

    $company = Company::create(['name' => 'Sized Co', 'size' => '100-250', 'city' => 'Philadelphia', 'region' => 'PA', 'industry' => 'Manufacturing']);
    $contact = Contact::create(['first_name' => 'Firm', 'email' => 'firm@x.test', 'company_id' => $company->id, 'lifecycle_stage' => 'lead']);

    ScoringRule::create(['name' => 'Mid-market', 'category' => 'firmographic', 'attribute' => 'company_size', 'operator' => 'equals', 'value' => '100-250', 'points' => 15, 'is_active' => true]);
    ScoringRule::create(['name' => 'Home market', 'category' => 'firmographic', 'attribute' => 'company_city', 'operator' => 'contains', 'value' => 'philadelphia', 'points' => 10, 'is_active' => true]);
    ScoringRule::create(['name' => 'Target vertical', 'category' => 'firmographic', 'attribute' => 'company_industry', 'operator' => 'equals', 'value' => 'Manufacturing', 'points' => 5, 'is_active' => true]);

    $scored = app(LeadScoringService::class)->apply($contact);
    expect($scored->lead_score)->toBe(30)
        ->and($scored->lifecycle_stage)->toBe('mql'); // 30 >= MQL, < SQL

    // A contact with no company matches none of them.
    $solo = Contact::create(['first_name' => 'Solo', 'email' => 'solo@x.test', 'lifecycle_stage' => 'lead']);
    expect(app(LeadScoringService::class)->apply($solo)->lead_score)->toBe(0);

    app(CurrentOrganization::class)->forget();
});
