<?php

namespace App\Services\Marketing;

use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Marketing\EmailBody;
use App\Marketing\MergeTags;
use App\Messaging\Contracts\MailProvider;
use App\Messaging\Contracts\SmsProvider;
use App\Messaging\EmailMessage;
use App\Messaging\SmsMessage;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\MarketingList;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Email/SMS campaign sending (EMAIL-001…020, SMS-001…008). Resolves recipients
 * from the campaign's list minus suppressions + opted-out contacts, dispatches
 * each via the configured provider, records per-recipient tracking rows, and
 * updates campaign stats. Central usage limit (`emails`) is enforced before a
 * send; a provider failure marks that recipient failed and the send continues.
 */
class CampaignService
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
        private UsageMeter $usage,
        private SuppressionService $suppressions,
        private MailProvider $mail,
        private SmsProvider $sms,
        private ListService $lists,
    ) {}

    public function send(Campaign $campaign): Campaign
    {
        if (in_array($campaign->status, ['sent', 'sending'], true)) {
            throw ValidationException::withMessages(['status' => __('This campaign has already been sent.')]);
        }

        if ($campaign->marketing_list_id === null) {
            throw ValidationException::withMessages(['audience' => __('Select an audience list before sending.')]);
        }

        // EMAIL-012: resolve through ListService so DYNAMIC list criteria are
        // honored — static membership alone silently skipped criteria members.
        $list = MarketingList::findOrFail($campaign->marketing_list_id);
        $contacts = $this->lists->members($list);
        $organization = $this->currentOrganization->get();

        // Central usage limit (ENTL) for email sends.
        if ($campaign->channel === 'email' && $organization !== null
            && ! $this->usage->withinLimit($organization, Limit::Emails, $contacts->count())) {
            throw ValidationException::withMessages([
                'audience' => __('Sending would exceed your plan\'s monthly email limit.'),
            ]);
        }

        $campaign->update(['status' => 'sending', 'stat_recipients' => $contacts->count()]);

        $sent = 0;
        $failed = 0;

        foreach ($contacts as $contact) {
            $address = $campaign->channel === 'email' ? $contact->email : $contact->phone;

            if ($address === null || $address === ''
                || $this->suppressions->isSuppressed($campaign->channel, $address)
                || ($campaign->channel === 'email' && ! $contact->email_opt_in)
                || ($campaign->channel === 'sms' && ! $contact->sms_opt_in)) {
                continue;
            }

            // EMAIL-015: with a B subject, recipients alternate deterministically.
            $variant = $campaign->subject_b !== null && $campaign->subject_b !== ''
                ? (($sent + $failed) % 2 === 0 ? 'a' : 'b')
                : null;

            $recipient = CampaignRecipient::firstOrCreate(
                ['campaign_id' => $campaign->id, 'contact_id' => $contact->id],
                ['organization_id' => $campaign->organization_id, 'address' => $address, 'token' => Str::random(48), 'status' => 'pending', 'variant' => $variant],
            );

            if ($recipient->status === 'sent') {
                continue; // idempotent re-run
            }

            $result = $campaign->channel === 'email'
                ? $this->mail->send($this->buildEmail($campaign, $contact, $recipient))
                : $this->sms->send(new SmsMessage($address, MergeTags::render((string) $campaign->body_text, $contact)));

            if ($result->accepted) {
                $recipient->update(['status' => 'sent', 'sent_at' => now()]);
                $sent++;
            } else {
                $recipient->update(['status' => 'failed', 'error' => $result->error]);
                $failed++;
            }
        }

        if ($campaign->channel === 'email' && $organization !== null && $sent > 0) {
            $this->usage->increment($organization, Limit::Emails, $sent);
        }

        $campaign->update([
            'status' => 'sent',
            'sent_at' => now(),
            'stat_sent' => $sent,
            'stat_bounced' => $failed,
        ]);

        $this->audit->log(
            'campaign.sent',
            context: ['name' => $campaign->name, 'channel' => $campaign->channel, 'sent' => $sent, 'failed' => $failed],
            resourceType: 'campaign',
            resourceId: (string) $campaign->id,
        );

        return $campaign->refresh();
    }

    private function buildEmail(Campaign $campaign, Contact $contact, CampaignRecipient $recipient): EmailMessage
    {
        $html = EmailBody::withTracking(MergeTags::render((string) $campaign->body_html, $contact), $recipient->token);

        // EMAIL-015: the B half of a split gets the B subject.
        $subject = $recipient->variant === 'b' && $campaign->subject_b !== null && $campaign->subject_b !== ''
            ? $campaign->subject_b
            : (string) $campaign->subject;

        return new EmailMessage(
            toEmail: (string) $contact->email,
            subject: MergeTags::render($subject, $contact),
            html: $html,
            fromEmail: $campaign->from_email ?: (string) config('marketing.from.email'),
            fromName: $campaign->from_name ?: (string) config('marketing.from.name'),
        );
    }

    /**
     * Subject A/B results (EMAIL-015): per-variant recipients, opens and open
     * rate, plus the current leader — only ever the tenant's own numbers.
     *
     * @return array{enabled: bool, variants: array<string, array{subject: string|null, recipients: int, opens: int, open_rate: float}>, leader: string|null}|null
     */
    public function abResults(Campaign $campaign): ?array
    {
        if ($campaign->subject_b === null || $campaign->subject_b === '') {
            return null;
        }

        $variants = [];
        foreach (['a' => $campaign->subject, 'b' => $campaign->subject_b] as $variant => $subject) {
            $recipients = CampaignRecipient::where('campaign_id', $campaign->id)->where('variant', $variant)->count();
            $opens = CampaignRecipient::where('campaign_id', $campaign->id)->where('variant', $variant)->whereNotNull('opened_at')->count();
            $variants[$variant] = [
                'subject' => $subject,
                'recipients' => $recipients,
                'opens' => $opens,
                'open_rate' => $recipients > 0 ? round($opens / $recipients * 100, 2) : 0.0,
            ];
        }

        $leader = $variants['a']['open_rate'] <=> $variants['b']['open_rate'];

        return [
            'enabled' => true,
            'variants' => $variants,
            'leader' => $leader === 0 ? null : ($leader > 0 ? 'a' : 'b'),
        ];
    }

    /**
     * Conversion attribution (EMAIL-019): recipient contacts whose deals
     * closed WON after the campaign was sent. A win that predates the send
     * never counts — the campaign cannot have converted it.
     *
     * @return array{customers: int, revenue: int}
     */
    public function conversions(Campaign $campaign): array
    {
        if ($campaign->sent_at === null) {
            return ['customers' => 0, 'revenue' => 0];
        }

        $contactIds = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'sent')->pluck('contact_id');

        $deals = Deal::where('status', 'won')
            ->whereIn('contact_id', $contactIds)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $campaign->sent_at)
            ->get(['contact_id', 'value']);

        return [
            'customers' => $deals->pluck('contact_id')->unique()->count(),
            'revenue' => (int) $deals->sum(fn (Deal $d) => (int) $d->value),
        ];
    }
}
