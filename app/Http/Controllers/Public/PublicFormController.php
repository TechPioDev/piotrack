<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Services\Marketing\LeadCaptureService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        $this->capture->capture($form, $data, $request->ip(), $request->userAgent(), $request->cookie('_pt_vid'));

        $settings = $form->settings ?? [];
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
