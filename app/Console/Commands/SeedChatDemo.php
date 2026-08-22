<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatEvent;
use App\Models\ChatMessage;
use App\Models\ChatWidget;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Organization;
use App\Services\Chat\DefaultChatFlow;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Populate the Website Chat module with a believable month of activity so a new
 * tenant can see what every page looks like before a single real visitor
 * arrives — an empty funnel is indistinguishable from a broken one.
 *
 * Everything it creates is ordinary data the app would have produced itself, so
 * the analytics are computed, never faked, and it can be removed again with
 * --fresh.
 */
class SeedChatDemo extends Command
{
    protected $signature = 'chat:demo
        {--organization= : Organization id (defaults to the first one)}
        {--fresh : Remove existing chat data for the organization first}';

    protected $description = 'Create a demo chat widget with sample conversations, leads and analytics';

    /** Where visitors realistically give up, worst last. */
    private const ABANDON_AT = ['q_size', 'q_provider', 'in_email', 'in_phone'];

    public function handle(CurrentOrganization $current): int
    {
        $organization = $this->option('organization')
            ? Organization::find((int) $this->option('organization'))
            : Organization::query()->orderBy('id')->first();

        if ($organization === null) {
            $this->error('No organization found. Create one first.');

            return self::FAILURE;
        }

        $current->set($organization);
        $this->info("Seeding Website Chat demo data for: {$organization->name}");

        if ($this->option('fresh')) {
            ChatEvent::query()->delete();
            ChatMessage::query()->delete();
            ChatConversation::query()->delete();
            ChatWidget::query()->forceDelete();
            $this->line('  Removed existing chat data.');
        }

        $widget = ChatWidget::create([
            'name' => 'Homepage — MSP qualification',
            'description' => 'Demo widget with a month of sample activity',
            'status' => 'active',
            'flow' => DefaultChatFlow::definition(),
            'theme' => [
                'title' => 'Chat with us',
                'company' => $organization->name,
                'accent' => '#0bb39e',
                'position' => 'bottom-right',
            ],
            'consent' => [
                'required' => true,
                'message' => 'We use this chat to respond to your request and may retain the conversation.',
            ],
            'settings' => ['language' => 'en', 'mode' => 'bot_then_human', 'teaser' => 'Need help with IT or cybersecurity?'],
            'targeting' => [],
            'allowed_domains' => [],
        ]);
        $this->line("  Widget created (key: {$widget->public_key}).");

        // Views and opens across the month: most visitors never open the chat,
        // which is what makes the funnel's first rungs meaningful.
        $this->seedEvents($widget, 'impression', 420);
        $this->seedEvents($widget, 'open', 68);

        $finished = [
            ['Michael', 'Rodriguez', 'michael.rodriguez@precisionmfg.test', 'Precision Manufacturing Group', 'cybersecurity', '51-250', 92, true],
            ['Sarah', 'Chen', 'sarah.chen@apexlegal.test', 'Apex Legal', 'managed_it', '11-50', 68, true],
            ['David', 'Okafor', 'david@harbourclinic.test', 'Harbour Clinic', 'm365', '11-50', 45, false],
            ['Priya', 'Nair', 'priya@westfieldlogistics.test', 'Westfield Logistics', 'cmmc', '51-250', 74, true],
            ['Tom', 'Bennett', 'tom@bennettbuilds.test', 'Bennett Builds', 'managed_it', '1-10', 22, false],
            ['Elena', 'Marks', 'elena@northgatefin.test', 'Northgate Financial', 'cybersecurity', '250+', 81, true],
        ];

        foreach ($finished as [$first, $last, $email, $company, $service, $size, $score, $meeting]) {
            $this->seedCompleted($widget, $first, $last, $email, $company, $service, $size, $score, $meeting);
        }
        $this->line('  '.count($finished).' completed conversations with contacts and leads.');

        // Abandoned conversations: these are what the drop-off report is for.
        $abandoned = 0;
        foreach (self::ABANDON_AT as $index => $node) {
            foreach (range(0, $index + 1) as $ignored) {
                $this->seedAbandoned($widget, $node);
                $abandoned++;
            }
        }
        $this->line("  {$abandoned} abandoned conversations across {$this->pluralise(count(self::ABANDON_AT))}.");

        $current->forget();

        $this->newLine();
        $this->info('Done. What to look at:');
        $this->line('  Website Chat → Conversations   the inbox, with hot leads flagged');
        $this->line('  Website Chat → Analytics       funnel, drop-off and per-widget numbers');
        $this->line('  Website Chat → Widgets         Settings and the conversation builder');
        $this->newLine();
        $this->line('  Remove it all again with:  php artisan chat:demo --fresh');

        return self::SUCCESS;
    }

    private function pluralise(int $count): string
    {
        return $count.' '.($count === 1 ? 'question' : 'questions');
    }

    private function seedEvents(ChatWidget $widget, string $type, int $count): void
    {
        $rows = [];
        foreach (range(1, $count) as $ignored) {
            $at = now()->subDays(random_int(0, 29))->subMinutes(random_int(0, 1439));
            $rows[] = [
                'organization_id' => $widget->organization_id,
                'chat_widget_id' => $widget->id,
                'type' => $type,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            ChatEvent::insert($chunk);
        }
    }

    private function seedCompleted(
        ChatWidget $widget,
        string $first,
        string $last,
        string $email,
        string $company,
        string $service,
        string $size,
        int $score,
        bool $meeting,
    ): void {
        $at = now()->subDays(random_int(1, 28));

        $contact = Contact::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $first,
                'last_name' => $last,
                'lead_source' => 'website_chat',
                'lifecycle_stage' => $score >= 60 ? 'sql' : 'lead',
                'lead_score' => $score,
            ],
        );

        $lead = Lead::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $first,
                'last_name' => $last,
                'company_name' => $company,
                'source' => 'website_chat',
                'status' => 'qualified',
                'lead_score' => $score,
            ],
        );

        $conversation = ChatConversation::create([
            'chat_widget_id' => $widget->id,
            'status' => $score >= 60 ? 'qualified' : 'converted',
            'visitor_id' => 'demo_'.strtolower($first),
            'contact_id' => $contact->id,
            'lead_id' => $lead->id,
            'lead_score' => $score,
            'answers' => [
                'service' => $service,
                'company_size' => $size,
                'current_provider' => $score > 50 ? 'yes' : 'no',
                'challenge' => 'security',
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => '215-555-0'.random_int(100, 199),
                'company_name' => $company,
                'wants_meeting' => $meeting ? 'yes' : 'no',
            ],
            'attribution' => [
                'source' => 'website_chat',
                'page' => 'https://example.test/'.($service === 'cybersecurity' ? 'cybersecurity' : 'managed-it'),
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => $service.'-campaign',
            ],
            'last_message_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $this->transcript($conversation, $first, $service, $at);

        foreach (array_filter(['start', 'lead', $score >= 60 ? 'qualified' : null, $meeting ? 'meeting' : null, 'complete']) as $type) {
            ChatEvent::create([
                'chat_widget_id' => $widget->id,
                'chat_conversation_id' => $conversation->id,
                'type' => $type,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    private function seedAbandoned(ChatWidget $widget, string $node): void
    {
        $at = now()->subDays(random_int(1, 28));

        // Whatever they answered before giving up, plus the cursor parked on the
        // question that lost them — which is exactly how drop-off is measured.
        $answers = ['_node' => $node, '_consent' => true, 'service' => 'managed_it'];  // matches q_service->field
        if (in_array($node, ['in_email', 'in_phone'], true)) {
            $answers['company_size'] = '11-50';
            $answers['current_provider'] = 'yes';
            $answers['challenge'] = 'slow_support';
            $answers['first_name'] = 'Visitor';
        }

        $conversation = ChatConversation::create([
            'chat_widget_id' => $widget->id,
            'status' => 'open',
            'visitor_id' => 'demo_anon_'.random_int(1000, 9999),
            'answers' => $answers,
            'attribution' => ['source' => 'website_chat', 'page' => 'https://example.test/managed-it'],
            'last_message_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        ChatEvent::create([
            'chat_widget_id' => $widget->id,
            'chat_conversation_id' => $conversation->id,
            'type' => 'start',
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function transcript(ChatConversation $conversation, string $first, string $service, Carbon $at): void
    {
        $lines = [
            ['bot', 'Hi! Looking for help with your business technology?'],
            ['bot', 'What can we help you with?'],
            ['visitor', $service === 'cybersecurity' ? 'Cybersecurity' : 'Managed IT Services'],
            ['bot', 'Approximately how many employees do you have?'],
            ['visitor', $conversation->answers['company_size'] ?? '11-50'],
            ['bot', 'Great — let me get a few details. What is your first name?'],
            ['visitor', $first],
            ['bot', 'Thanks! Someone will be in touch shortly.'],
        ];

        foreach ($lines as $i => [$role, $body]) {
            ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role' => $role,
                'body' => $body,
                'created_at' => $at->copy()->addSeconds($i * 20),
                'updated_at' => $at->copy()->addSeconds($i * 20),
            ]);
        }
    }
}
