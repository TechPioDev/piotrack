<?php

declare(strict_types=1);

/**
 * The seeding command exists so a tenant's published pages are not brochures.
 * Two properties matter: it must reach every published page, and re-running it
 * must not duplicate anything or overwrite an edit — someone will run it twice.
 */

use App\Models\Form;
use App\Models\PageSection;
use App\Models\SitePage;
use App\Services\Web\SiteBuilderService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org] = makeOrganization('Seeded MSP');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);

    $builder = app(SiteBuilderService::class);
    foreach (['Managed IT', 'Cybersecurity'] as $title) {
        $page = $builder->createPage(['title' => $title, 'type' => 'service']);
        $builder->addSection($page, ['type' => 'cta', 'heading' => 'Book a call']);
        $builder->publish($page);
    }
    // A draft must be left alone: it is not a marketing surface yet.
    $builder->createPage(['title' => 'Unfinished', 'type' => 'service']);
    app(CurrentOrganization::class)->forget();
});

it('gives every published page an faq and a form', function () {
    $this->artisan('site:marketing-content', ['--org' => $this->org->id])->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);

    $published = SitePage::where('status', SitePage::STATUS_PUBLISHED)->get();
    expect($published)->toHaveCount(2);

    foreach ($published as $page) {
        expect($page->form_id)->not->toBeNull();
        expect(PageSection::where('site_page_id', $page->id)->where('type', 'faq')->count())->toBe(1);
    }

    $draft = SitePage::where('title', 'Unfinished')->firstOrFail();
    expect($draft->form_id)->toBeNull();
    expect(PageSection::where('site_page_id', $draft->id)->where('type', 'faq')->count())->toBe(0);

    app(CurrentOrganization::class)->forget();
});

it('can be run twice without duplicating anything', function () {
    $this->artisan('site:marketing-content', ['--org' => $this->org->id])->assertSuccessful();
    $this->artisan('site:marketing-content', ['--org' => $this->org->id])->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);

    expect(Form::count())->toBe(1);
    foreach (SitePage::where('status', SitePage::STATUS_PUBLISHED)->get() as $page) {
        expect(PageSection::where('site_page_id', $page->id)->where('type', 'faq')->count())->toBe(1);
    }

    app(CurrentOrganization::class)->forget();
});

it('does not publish invented commitments', function () {
    $this->artisan('site:marketing-content', ['--org' => $this->org->id])->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);
    $faq = PageSection::where('type', 'faq')->firstOrFail();
    $text = implode(' ', $faq->settings['items']);
    app(CurrentOrganization::class)->forget();

    // Response times, cover hours and contract terms are commitments to a
    // customer. Seeded copy must not state any on the business's behalf.
    expect($text)->not->toMatch('/\b\d+\s*(minute|hour|month|year|day)s?\b/i')
        ->and($text)->not->toContain('24/7')
        ->and($text)->not->toMatch('/\b(guarantee|guaranteed)\b/i');
});
