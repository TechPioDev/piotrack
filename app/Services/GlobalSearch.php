<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Deal;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Tenant-scoped global search (SRCH-001/002). Searches contacts, companies,
 * leads, deals, campaigns, content, members, teams, invoices and files —
 * grouped by type, filtered by the viewer's permissions — and keeps a short
 * per-user list of recent search terms for the palette to suggest.
 */
class GlobalSearch
{
    /** How many recent terms are remembered per user+org (SRCH-002). */
    public const RECENT_LIMIT = 5;

    /**
     * @return array<int, array{type: string, label: string, items: list<array{title: string, subtitle: ?string, url: string}>}>
     */
    public function search(User $user, Organization $organization, string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $like = '%'.str_replace('%', '\%', $term).'%';
        $groups = [];

        // Organizations the user belongs to.
        $orgs = $user->activeOrganizations()->whereLike('name', $like)->limit(5)->get();
        if ($orgs->isNotEmpty()) {
            $groups[] = $this->group('Organizations', $orgs->map(fn (Organization $o) => [
                'title' => $o->name,
                'subtitle' => null,
                'url' => route('billing.index'),
            ])->all());
        }

        if (Gate::forUser($user)->allows('crm.contact.read')) {
            $contacts = Contact::query()
                ->where(fn ($q) => $q->whereLike('first_name', $like)->orWhereLike('last_name', $like)->orWhereLike('email', $like))
                ->limit(5)->get();
            if ($contacts->isNotEmpty()) {
                $groups[] = $this->group('Contacts', $contacts->map(fn (Contact $c) => [
                    'title' => $c->fullName(),
                    'subtitle' => $c->email,
                    'url' => route('crm.contacts.show', $c->id),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('crm.company.read')) {
            $companies = Company::query()
                ->where(fn ($q) => $q->whereLike('name', $like)->orWhereLike('domain', $like))
                ->limit(5)->get();
            if ($companies->isNotEmpty()) {
                $groups[] = $this->group('Companies', $companies->map(fn (Company $c) => [
                    'title' => $c->name,
                    'subtitle' => $c->domain,
                    'url' => route('crm.companies.show', $c->id),
                ])->all());
            }
        }

        // Leads are contacts still in the lead stages — surfaced as their own
        // group so a rep can jump straight to the working queue (SRCH-001).
        if (Gate::forUser($user)->allows('crm.contact.read')) {
            $leads = Contact::query()
                ->whereIn('lifecycle_stage', ['lead', 'mql', 'sql'])
                ->where(fn ($q) => $q->whereLike('first_name', $like)->orWhereLike('last_name', $like)->orWhereLike('email', $like))
                ->limit(5)->get();
            if ($leads->isNotEmpty()) {
                $groups[] = $this->group('Leads', $leads->map(fn (Contact $c) => [
                    'title' => $c->fullName(),
                    'subtitle' => strtoupper($c->lifecycle_stage),
                    'url' => route('crm.contacts.show', $c->id),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('marketing.view')) {
            $campaigns = Campaign::whereLike('name', $like)->limit(5)->get();
            if ($campaigns->isNotEmpty()) {
                $groups[] = $this->group('Campaigns', $campaigns->map(fn (Campaign $c) => [
                    'title' => $c->name,
                    'subtitle' => ucfirst((string) $c->status),
                    'url' => route('marketing.campaigns.show', $c->id),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('content.view')) {
            $pieces = ContentPiece::whereLike('title', $like)->limit(5)->get();
            if ($pieces->isNotEmpty()) {
                $groups[] = $this->group('Content', $pieces->map(fn (ContentPiece $p) => [
                    'title' => $p->title,
                    'subtitle' => ucfirst((string) $p->status),
                    'url' => route('content.pieces.show', $p->id),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('crm.deal.read')) {
            $deals = Deal::whereLike('name', $like)->limit(5)->get();
            if ($deals->isNotEmpty()) {
                $groups[] = $this->group('Deals', $deals->map(fn (Deal $d) => [
                    'title' => $d->name,
                    'subtitle' => ucfirst($d->status),
                    'url' => route('crm.deals.show', $d->id),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('members.view')) {
            $members = $organization->members()
                ->where(fn ($q) => $q->whereLike('name', $like)->orWhereLike('email', $like))
                ->limit(5)->get();
            if ($members->isNotEmpty()) {
                $groups[] = $this->group('Members', $members->map(fn (User $m) => [
                    'title' => $m->name,
                    'subtitle' => $m->email,
                    'url' => route('members.index'),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('teams.view')) {
            $teams = Team::whereLike('name', $like)->limit(5)->get();
            if ($teams->isNotEmpty()) {
                $groups[] = $this->group('Teams', $teams->map(fn (Team $t) => [
                    'title' => $t->name,
                    'subtitle' => null,
                    'url' => route('teams.index'),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('billing.view')) {
            $invoices = Invoice::where('organization_id', $organization->id)
                ->whereLike('number', $like)->limit(5)->get();
            if ($invoices->isNotEmpty()) {
                $groups[] = $this->group('Invoices', $invoices->map(fn (Invoice $i) => [
                    'title' => $i->number,
                    'subtitle' => ucfirst($i->status),
                    'url' => route('billing.invoices.show', $i->id),
                ])->all());
            }
        }

        if (Gate::forUser($user)->allows('files.view')) {
            $files = File::whereLike('name', $like)->limit(5)->get();
            if ($files->isNotEmpty()) {
                $groups[] = $this->group('Files', $files->map(fn (File $f) => [
                    'title' => $f->name,
                    'subtitle' => null,
                    'url' => route('files.index'),
                ])->all());
            }
        }

        return $groups;
    }

    /**
     * Remember a term the user actually searched (SRCH-002), newest first.
     */
    public function rememberTerm(User $user, Organization $organization, string $term): void
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return;
        }

        $key = $this->recentKey($user, $organization);
        $recent = array_values(array_filter(
            (array) Cache::get($key, []),
            fn ($t) => is_string($t) && mb_strtolower($t) !== mb_strtolower($term),
        ));
        array_unshift($recent, $term);

        Cache::put($key, array_slice($recent, 0, self::RECENT_LIMIT), now()->addDays(30));
    }

    /**
     * @return list<string>
     */
    public function recentTerms(User $user, Organization $organization): array
    {
        return array_values(array_filter((array) Cache::get($this->recentKey($user, $organization), []), 'is_string'));
    }

    private function recentKey(User $user, Organization $organization): string
    {
        return "search.recent.{$organization->id}.{$user->id}";
    }

    /**
     * @param  list<array{title: string, subtitle: ?string, url: string}>  $items
     * @return array{type: string, label: string, items: list<array{title: string, subtitle: ?string, url: string}>}
     */
    private function group(string $label, array $items): array
    {
        return ['type' => strtolower($label), 'label' => $label, 'items' => $items];
    }
}
