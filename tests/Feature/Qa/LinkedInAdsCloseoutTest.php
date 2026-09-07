<?php

declare(strict_types=1);

/**
 * LinkedIn Advertising close-out (Phase 37 — LIAD-002/013/014/015/016/017).
 *
 * Content + case-study promotion into draft campaigns, matched-audience
 * attachment, ABM tier campaigns, the lead-gen form CSV import (Campaign
 * Manager's own export format), and the Campaign Manager brief. Live
 * Marketing API delivery/audience push/form sync stay behind ADR-0006 and
 * are asserted nowhere.
 */

use App\Models\AdCampaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\RetargetingAudience;
use App\Models\TargetAccount;
use App\Services\Advertising\LinkedInAdsService;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('LIAD Org');
    subscribeOrganization($this->org, 'professional');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('promotes content and case studies into draft LinkedIn campaigns with creative from the piece', function () {
    $article = ContentPiece::create(['title' => 'The MSP guide to co-managed IT', 'slug' => 'co-managed', 'content_type' => 'guide', 'status' => 'published', 'excerpt' => 'What co-managed IT costs, and when it beats fully managed.', 'url' => 'https://example.com/co-managed']);

    $this->actingAs($this->owner)
        ->post(route('ads.linkedin.promote-content'), ['content_piece_id' => $article->id])
        ->assertRedirect();

    $campaign = AdCampaign::where('platform', 'linkedin')->firstOrFail();
    expect($campaign->status)->toBe('draft')
        ->and($campaign->type)->toBe('sponsored_content')
        ->and($campaign->objective)->toBe('awareness')
        ->and($campaign->name)->toBe('Promote: The MSP guide to co-managed IT');

    $ad = $campaign->groups()->firstOrFail()->ads()->firstOrFail();
    expect($ad->status)->toBe('draft')
        ->and($ad->headline)->toBe('The MSP guide to co-managed IT')
        ->and($ad->body)->toBe('What co-managed IT costs, and when it beats fully managed.')
        ->and($ad->destination_url)->toBe('https://example.com/co-managed');

    // Idempotent: promoting again never stacks a second campaign.
    $this->actingAs($this->owner)->post(route('ads.linkedin.promote-content'), ['content_piece_id' => $article->id])->assertRedirect();
    expect(AdCampaign::count())->toBe(1);

    // LIAD-017: case studies are bottom-funnel proof — conversions objective.
    $caseStudy = ContentPiece::create(['title' => 'How a 40-seat firm cut downtime 90%', 'slug' => 'downtime-cs', 'content_type' => 'case_study', 'status' => 'published']);
    $this->actingAs($this->owner)->post(route('ads.linkedin.promote-content'), ['content_piece_id' => $caseStudy->id])->assertRedirect();

    $csCampaign = AdCampaign::where('targeting->content_piece_id', $caseStudy->id)->firstOrFail();
    expect($csCampaign->objective)->toBe('conversions')
        ->and($csCampaign->name)->toStartWith('Case study:');
});

it('attaches matched audiences to LinkedIn campaigns only, tenant-safely', function () {
    $linkedin = AdCampaign::create(['platform' => 'linkedin', 'name' => 'LI retarget', 'objective' => 'leads', 'daily_budget' => 2000]);
    $google = AdCampaign::create(['platform' => 'google_search', 'name' => 'Search', 'objective' => 'leads', 'daily_budget' => 2000]);
    $audience = RetargetingAudience::create(['name' => 'Site visitors', 'source' => 'rules', 'member_count' => 12]);

    $this->actingAs($this->owner)
        ->post(route('ads.campaigns.audience', $linkedin), ['audience_id' => $audience->id])
        ->assertRedirect();

    $targeting = $linkedin->refresh()->targeting;
    expect($targeting['audience_id'])->toBe($audience->id)
        ->and($targeting['audience_name'])->toBe('Site visitors');

    // Non-LinkedIn campaigns refuse matched audiences.
    $this->actingAs($this->owner)->from(route('ads.campaigns.show', $google))
        ->post(route('ads.campaigns.audience', $google), ['audience_id' => $audience->id])
        ->assertRedirect()->assertSessionHasErrors('audience_id');

    // Another tenant's audience can never be attached.
    [$otherOrg] = makeOrganization('Other LIAD Org');
    app(CurrentOrganization::class)->set($otherOrg);
    $foreign = RetargetingAudience::create(['name' => 'Foreign', 'source' => 'rules']);
    app(CurrentOrganization::class)->set($this->org);

    $this->actingAs($this->owner)->from(route('ads.campaigns.show', $linkedin))
        ->post(route('ads.campaigns.audience', $linkedin), ['audience_id' => $foreign->id])
        ->assertRedirect()->assertSessionHasErrors('audience_id');
});

it('creates the ABM tier campaign on the committee audience, idempotently', function () {
    $company = Company::create(['name' => 'Tiered Co', 'website' => 'https://tiered.example']);
    Contact::create(['first_name' => 'Cee', 'email' => 'cee@tiered.example', 'company_id' => $company->id, 'email_opt_in' => true]);
    TargetAccount::create(['company_id' => $company->id, 'tier' => 1, 'status' => 'active']);

    $this->actingAs($this->owner)->post(route('ads.linkedin.abm'), ['tier' => 1])->assertRedirect();

    $campaign = AdCampaign::where('platform', 'linkedin')->where('targeting->abm_tier', 1)->firstOrFail();
    $audience = RetargetingAudience::where('name', 'ABM Tier 1 committee')->firstOrFail();
    expect($campaign->status)->toBe('draft')
        ->and($campaign->type)->toBe('abm')
        ->and($campaign->targeting['audience_id'])->toBe($audience->id)
        ->and($audience->member_count)->toBeGreaterThanOrEqual(1);

    // Second click refreshes rather than duplicating.
    $this->actingAs($this->owner)->post(route('ads.linkedin.abm'), ['tier' => 1])->assertRedirect();
    expect(AdCampaign::where('targeting->abm_tier', 1)->count())->toBe(1);
});

it('imports Campaign Manager lead-gen CSV exports without overwriting first-touch sources', function () {
    Contact::create(['first_name' => 'Existing', 'email' => 'known@x.com', 'lead_source' => 'referral', 'email_opt_in' => true]);

    $csv = implode("\n", [
        'First Name,Last Name,Email Address,Job Title,Campaign Name',
        'Nia,Owens,nia@newco.example,IT Director,MSP awareness Q3',
        'Known,Person,known@x.com,COO,MSP awareness Q3',
        'Bad,Row,not-an-email,CEO,MSP awareness Q3',
    ]);
    $path = storage_path('framework/li-leads-'.uniqid().'.csv');
    file_put_contents($path, $csv);

    $counts = app(LinkedInAdsService::class)->importLeads($path);
    expect($counts)->toBe(['created' => 1, 'updated' => 1, 'skipped' => 1]);

    $nia = Contact::firstWhere('email', 'nia@newco.example');
    expect($nia->lead_source)->toBe('linkedin')
        ->and($nia->lifecycle_stage)->toBe('lead')
        ->and($nia->title)->toBe('IT Director')
        ->and($nia->campaign)->toBe('MSP awareness Q3');

    // The existing contact keeps its first-touch source; blanks were filled.
    $known = Contact::firstWhere('email', 'known@x.com');
    expect($known->lead_source)->toBe('referral')
        ->and($known->title)->toBe('COO');

    // The HTTP endpoint wires the same import behind ads.campaigns.manage.
    $upload = new UploadedFile($path, 'leads.csv', 'text/csv', null, true);
    $this->actingAs($this->owner)->post(route('ads.linkedin.leads'), ['file' => $upload])->assertRedirect()->assertSessionHasNoErrors();

    @unlink($path);
});

it('produces the Campaign Manager brief with settings, facets, audience and creatives', function () {
    $campaign = AdCampaign::create([
        'platform' => 'linkedin', 'name' => 'CIO awareness', 'objective' => 'awareness', 'daily_budget' => 7500,
        'targeting' => ['job_titles' => ['CIO', 'CTO'], 'company_size' => '51-200', 'seniority' => 'director+'],
    ]);
    $audience = RetargetingAudience::create(['name' => 'Warm visitors', 'source' => 'rules', 'member_count' => 44]);
    app(LinkedInAdsService::class)->attachAudience($campaign, $audience);
    $group = $campaign->groups()->create(['name' => 'Creatives', 'status' => 'draft']);
    $group->ads()->create(['name' => 'A1', 'headline' => 'IT that answers to the board', 'body' => 'Uptime and security your CFO can read.', 'destination_url' => 'https://example.com/cio', 'status' => 'draft']);

    $csv = $this->actingAs($this->owner)
        ->get(route('ads.campaigns.brief', $campaign))
        ->assertOk()->assertDownload('linkedin-cio-awareness-brief.csv')
        ->streamedContent();

    expect($csv)->toContain('Campaign,Objective,awareness')
        ->and($csv)->toContain('Campaign,"Daily budget",75.00')
        ->and($csv)->toContain('Targeting,job_titles,"CIO; CTO"')
        ->and($csv)->toContain('Targeting,company_size,51-200')
        ->and($csv)->toContain('"Matched audience","Warm visitors"')
        ->and($csv)->toContain('44 members')
        ->and($csv)->toContain('Creative,"IT that answers to the board","Uptime and security your CFO can read."');

    // The brief is LinkedIn-only.
    $google = AdCampaign::create(['platform' => 'google_search', 'name' => 'G', 'objective' => 'leads', 'daily_budget' => 100]);
    $this->actingAs($this->owner)->from(route('ads.campaigns.show', $google))
        ->get(route('ads.campaigns.brief', $google))
        ->assertRedirect()->assertSessionHasErrors('campaign');
});
