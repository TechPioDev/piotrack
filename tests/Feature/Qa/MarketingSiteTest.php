<?php

use App\Models\AuditLog;
use App\Models\ContactMessage;
use Inertia\Testing\AssertableInertia;

/*
 * MSITE — public product marketing pages: SEO meta rendered server-side,
 * FAQPage JSON-LD, contact form storage, sitemap.
 */

dataset('marketing pages', [
    '/features' => ['/features', 'site/features', 'MSP Marketing Software Features'],
    '/how-it-works' => ['/how-it-works', 'site/how-it-works', 'How Piotrack Works'],
    '/results' => ['/results', 'site/results', 'MSP Marketing Results You Can Prove'],
    '/about' => ['/about', 'site/about', 'About Piotrack'],
    '/contact' => ['/contact', 'site/contact', 'Contact Piotrack'],
    '/faq' => ['/faq', 'site/faq', 'Piotrack FAQ'],
]);

it('serves each marketing page with server-rendered SEO meta', function (string $path, string $component, string $title) {
    $response = $this->get($path);

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component($component))
        // Crawler-facing tags must be in the initial HTML — the app has no SSR.
        ->assertSee('<title inertia>'.$title, false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('<link rel="canonical" href="'.url($path).'">', false);
})->with('marketing pages');

it('renders FAQPage JSON-LD on the FAQ page from the same items the page shows', function () {
    $response = $this->get('/faq');

    $response->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('FAQPage', false)
        ->assertSee('What is Piotrack?', false)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('site/faq')
            ->has('items', 10)
            ->where('items.0.q', 'What is Piotrack?'));
});

it('renders Organization JSON-LD on the about page', function () {
    $this->get('/about')->assertOk()->assertSee('"@type":"Organization"', false);
});

it('stores a contact message and audits it at platform level', function () {
    $response = $this->post('/contact', [
        'name' => 'Jordan Reyes',
        'email' => 'Jordan@ExampleMSP.com',
        'company' => 'Example MSP',
        'message' => 'Interested in a demo for our 12-person MSP.',
    ]);

    $response->assertRedirect()->assertSessionHas('status');

    $message = ContactMessage::sole();
    expect($message->email)->toBe('jordan@examplemsp.com')
        ->and($message->company)->toBe('Example MSP')
        ->and($message->source)->toBe('contact-page');

    expect(AuditLog::where('action', 'contact.message_received')->whereNull('organization_id')->exists())->toBeTrue();
});

it('validates the contact form', function () {
    $this->from('/contact')
        ->post('/contact', ['name' => '', 'email' => 'not-an-email', 'message' => ''])
        ->assertRedirect('/contact')
        ->assertSessionHasErrors(['name', 'email', 'message']);

    expect(ContactMessage::count())->toBe(0);
});

it('silently discards honeypot contact submissions', function () {
    $this->post('/contact', [
        'name' => 'Bot',
        'email' => 'bot@spam.test',
        'message' => 'spam',
        'website' => 'http://spam.test',
    ])->assertRedirect()->assertSessionHas('status');

    expect(ContactMessage::count())->toBe(0);
});

it('serves a sitemap listing every public product page', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()->assertHeader('Content-Type', 'application/xml');

    foreach (['/', '/features', '/how-it-works', '/results', '/about', '/contact', '/faq'] as $path) {
        $response->assertSee('<loc>'.url($path).'</loc>', false);
    }
});

it('serves the homepage with server-rendered SEO meta', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<title inertia>Piotrack', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('<link rel="canonical" href="'.url('/').'">', false);
});
