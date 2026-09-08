<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\File;
use App\Models\Form;
use App\Models\Organization;
use App\Services\Marketing\LeadCaptureService;
use App\Services\Marketing\MarketingTrigger;
use App\Services\Web\PageExperiments;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public, unauthenticated form rendering + submission. The tenant is resolved
 * from the form's slug (globally unique) and set as the current organization so
 * lead capture runs tenant-scoped. A honeypot field + route throttling guard
 * against bots.
 */
class PublicFormController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private LeadCaptureService $capture,
    ) {}

    public function show(string $slug): View
    {
        $form = $this->resolve($slug);

        return view('public.form', ['form' => $form]);
    }

    public function submit(Request $request, string $slug): RedirectResponse|View
    {
        $form = $this->resolve($slug);

        // Honeypot: bots fill the hidden "website" field — silently thank them.
        if ($request->filled('website')) {
            return $this->thanks($form);
        }

        $data = $request->validate($this->rulesFor($form));

        $contact = $this->capture->capture($form, $data, $request->ip(), $request->userAgent(), $request->cookie('_pt_vid'));

        // WEB-037: an experiment cookie riding the submission converts the
        // variant the visitor was exposed to.
        app(PageExperiments::class)->convertFromCookies(array_filter(
            $request->cookies->all(),
            fn ($name) => str_starts_with((string) $name, PageExperiments::COOKIE_PREFIX),
            ARRAY_FILTER_USE_KEY,
        ));

        $settings = $form->settings ?? [];

        // WEB-022: a lead-magnet form answers with a signed, expiring download
        // link — the asset is gated behind the submission, never a public URL.
        if (! empty($settings['lead_magnet_file_id'])) {
            $file = File::find((int) $settings['lead_magnet_file_id']);
            if ($file !== null) {
                return view('public.message', [
                    'title' => __('Thank you'),
                    'message' => __('Your download is ready.'),
                    // AUTO-005: the signed URL carries the capturing contact,
                    // tamper-proof, so redemption can fire download workflows.
                    'downloadUrl' => URL::temporarySignedRoute('public.magnet', now()->addDays(7), ['file' => $file->id, 'contact' => $contact->id]),
                    'downloadName' => $file->name,
                ]);
            }
        }

        if (! empty($settings['redirect_url'])) {
            return redirect()->away((string) $settings['redirect_url']);
        }

        return $this->thanks($form);
    }

    /**
     * @return array<string, list<string>>
     */
    private function rulesFor(Form $form): array
    {
        $rules = [];

        foreach ($form->fields as $field) {
            $set = [($field['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:1000'];

            if (($field['type'] ?? '') === 'email') {
                $set[] = 'email';
            }

            $rules[(string) $field['name']] = $set;
        }

        return $rules;
    }

    /**
     * WEB-022: gated lead-magnet delivery. Only a signed URL (minted after a
     * form submission) reaches the file; every download is counted.
     */
    public function magnet(Request $request, int $file): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $record = File::withoutGlobalScope('tenant')->find($file);
        abort_if($record === null, 404);

        $record->increment('download_count');

        // AUTO-005: an actual redemption by the captured contact fires
        // content-download workflows. The contact id rides the SIGNED url, so
        // it cannot be forged; the file's own org scopes the workflow lookup.
        $contactId = (int) $request->query('contact', 0);
        if ($contactId > 0) {
            $organization = Organization::find($record->organization_id);
            $contact = Contact::withoutGlobalScope('tenant')->find($contactId);
            if ($organization !== null && $contact !== null && (int) $contact->organization_id === (int) $organization->id) {
                $this->currentOrganization->set($organization);
                app(MarketingTrigger::class)->fire('content_download', $contact, ['file_id' => $record->id]);
            }
        }

        return Storage::disk($record->disk)->download($record->path, $record->name);
    }

    private function resolve(string $slug): Form
    {
        $form = Form::withoutGlobalScope('tenant')
            ->where('slug', $slug)->where('status', 'published')->first();

        abort_if($form === null, 404);

        $organization = $form->organization()->first();
        abort_if($organization === null, 404);

        $this->currentOrganization->set($organization);

        return $form;
    }

    private function thanks(Form $form): View
    {
        $message = $form->settings['success_message'] ?? __('Thank you! We will be in touch shortly.');

        return view('public.message', ['title' => __('Thank you'), 'message' => $message]);
    }
}
