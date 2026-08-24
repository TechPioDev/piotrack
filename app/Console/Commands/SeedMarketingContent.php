<?php

namespace App\Console\Commands;

use App\Models\Form;
use App\Models\Organization;
use App\Models\PageSection;
use App\Models\SitePage;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;

/**
 * Give every published page the two things that turn it from a brochure into a
 * marketing page: answers to the questions a buyer actually asks, and somewhere
 * to put their details.
 *
 * The FAQ copy is a starting point, written to be true of any competent MSP and
 * deliberately free of numbers — no invented response times, contract terms or
 * hours of cover. Those are commitments to customers and belong to the business,
 * not to a seeding command. Edit them in Website → Pages before the site is
 * publicly reachable.
 *
 * Idempotent: a page that already has an FAQ section or a form is left alone, so
 * this can be re-run without duplicating anything or overwriting edits.
 */
class SeedMarketingContent extends Command
{
    protected $signature = 'site:marketing-content
                            {--org= : Organization id, defaults to every organization}';

    protected $description = 'Add an FAQ section and a contact form to published website pages';

    /**
     * Questions a prospect asks before they call. The answers commit to a way of
     * working rather than to a number, so they are safe to publish as written and
     * still worth replacing with specifics.
     *
     * @var list<string>
     */
    private const FAQS = [
        'How quickly will someone respond when something breaks? Every ticket is triaged as it arrives, and urgent issues go to the front of the queue. Your agreement sets the response times we commit to, and we report against them each month.',
        'Do we have to replace our existing hardware? No. We start by documenting what you already have, then plan any replacements around your budget and renewal dates rather than all at once.',
        'What actually happens during onboarding? We document your systems, take over monitoring and patching, and agree an escalation path before anything changes. Your team keeps working normally throughout.',
        'Can you work alongside the IT person we already have? Yes. Co-managed IT is common: we carry the monitoring, patching and out-of-hours load so your internal person can focus on the work only they can do.',
        'Are we tied into a long contract? The term and the notice period are set out in your agreement before you sign anything. We would rather earn the renewal than rely on the paperwork.',
    ];

    public function handle(CurrentOrganization $current): int
    {
        $organizations = $this->option('org')
            ? Organization::whereKey($this->option('org'))->get()
            : Organization::all();

        if ($organizations->isEmpty()) {
            $this->error('No organizations found.');

            return self::FAILURE;
        }

        foreach ($organizations as $organization) {
            $current->set($organization);
            $this->line('');
            $this->info($organization->name);

            $form = $this->contactForm();
            $this->line('  contact form: '.$form->slug.' ('.count($form->fields).' fields)');

            $pages = SitePage::where('status', SitePage::STATUS_PUBLISHED)->get();
            if ($pages->isEmpty()) {
                $this->line('  no published pages');

                continue;
            }

            foreach ($pages as $page) {
                $this->line('  '.$page->slug.$this->applyTo($page, $form));
            }
        }

        $current->forget();

        $this->line('');
        $this->comment('The FAQ answers are a starting point. Replace them with your own');
        $this->comment('specifics — response times, cover hours, contract terms — before');
        $this->comment('this site is reachable from the internet.');

        return self::SUCCESS;
    }

    /** The organization's contact form, created once and reused. */
    private function contactForm(): Form
    {
        $existing = Form::where('slug', 'like', 'contact-%')->first();
        if ($existing !== null) {
            return $existing;
        }

        return Form::create([
            'name' => 'Contact us',
            'slug' => 'contact-'.bin2hex(random_bytes(4)),
            'status' => 'published',
            'lifecycle_stage' => 'lead',
            'fields' => [
                ['name' => 'first_name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Work email', 'type' => 'email', 'required' => true],
                ['name' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => false],
                ['name' => 'message', 'label' => 'What do you need help with?', 'type' => 'textarea', 'required' => false],
            ],
            'settings' => ['button_label' => 'Request a callback'],
        ]);
    }

    /** Returns a short description of what changed, for the console. */
    private function applyTo(SitePage $page, Form $form): string
    {
        $changes = [];

        if ($page->form_id === null) {
            $page->form_id = $form->id;
            $page->save();
            $changes[] = 'form attached';
        }

        $hasFaq = PageSection::where('site_page_id', $page->id)->where('type', 'faq')->exists();
        if (! $hasFaq) {
            // After the last existing section, so the FAQ sits above the contact
            // block and answers the objection before asking for details.
            $last = (int) PageSection::where('site_page_id', $page->id)->max('sort_order');

            PageSection::create([
                'site_page_id' => $page->id,
                'type' => 'faq',
                'heading' => 'Questions we get asked',
                'sort_order' => $last + 1,
                'is_visible' => true,
                'settings' => ['items' => self::FAQS],
            ]);
            $changes[] = 'faq added';
        }

        return $changes === [] ? '  — already had both' : '  — '.implode(', ', $changes);
    }
}
