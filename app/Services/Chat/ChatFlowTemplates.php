<?php

namespace App\Services\Chat;

/**
 * Starter conversations a tenant can drop into a widget and then edit (spec §57),
 * organised by type of business (CHAT-055). Templates are ordinary flow graphs —
 * nothing about them is special or locked, so once applied they behave exactly
 * like a hand-built conversation.
 *
 * Every template asks for an email before it saves a lead, uses the contact
 * fields the capture service reads (first_name, email, phone, company_name),
 * and sends existing customers to a support ticket rather than to sales.
 */
class ChatFlowTemplates
{
    public const CATEGORIES = [
        'IT & managed services',
        'Software & agencies',
        'Professional services',
        'Local services',
        'Online store',
        'Any business',
    ];

    /**
     * The gallery: each template with its category and its conversation, so the
     * builder can preview it and load it without saving anything.
     *
     * @return list<array{key: string, name: string, description: string, category: string, steps: int, flow: array{start: string, nodes: array<string, array<string, mixed>>}}>
     */
    public function catalog(): array
    {
        $all = $this->all();
        usort($all, fn (array $a, array $b) => array_search($a['category'], self::CATEGORIES, true) <=> array_search($b['category'], self::CATEGORIES, true));

        return array_map(fn (array $t) => [
            'key' => $t['key'],
            'name' => $t['name'],
            'description' => $t['description'],
            'category' => $t['category'],
            'steps' => count($t['flow']['nodes']),
            'flow' => $t['flow'],
        ], $all);
    }

    /**
     * @return array{start: string, nodes: array<string, array<string, mixed>>}|null
     */
    public function flow(string $key): ?array
    {
        foreach ($this->all() as $template) {
            if ($template['key'] === $key) {
                return $template['flow'];
            }
        }

        return null;
    }

    /**
     * Put a real time-picker in front of every "we'll book you in" ending.
     *
     * A template used to finish a meeting by handing over a link to the booking
     * page, which meant "Yes, book it" ended the conversation and started a
     * second one in another tab - and if the tenant had no booking page live,
     * the visitor was told to pick a time and given nothing at all. So every
     * ending that promises a meeting gets a booking step in front of it: free
     * times as buttons, right there in the chat. The original ending stays as
     * the fallback, so a tenant with no booking page, or no free slots, still
     * gets the old behaviour rather than a dead end.
     *
     * @param  array{start: string, nodes: array<string, array<string, mixed>>}  $flow
     * @return array{start: string, nodes: array<string, array<string, mixed>>}
     */
    private function withInChatBooking(array $flow): array
    {
        $nodes = $flow['nodes'];

        foreach ($flow['nodes'] as $id => $node) {
            if (($node['type'] ?? '') !== 'end' || ($node['outcome'] ?? '') !== 'meeting') {
                continue;
            }

            // An ending that is already a booking step's fallback is where a
            // visitor lands when there were no times to offer. Putting another
            // time-picker in front of it would ask twice and answer once.
            $alreadyOffered = false;
            foreach ($flow['nodes'] as $other) {
                if (($other['type'] ?? '') === 'booking' && in_array($id, [$other['fallback'] ?? null, $other['next'] ?? null], true)) {
                    $alreadyOffered = true;
                }
            }
            if ($alreadyOffered) {
                continue;
            }

            $pick = 'bk_'.$id;
            $booked = $id.'_booked';
            if (isset($nodes[$pick])) {
                continue;
            }

            $nodes[$pick] = [
                'type' => 'booking',
                'text' => 'Pick a time that suits you:',
                'next' => $booked,
                'fallback' => (string) $id,
            ];
            $nodes[$booked] = [
                'type' => 'end',
                'outcome' => 'booked',
                'text' => 'You are booked in — the invitation is on its way to your inbox.',
            ];

            // Everything that led to the ending now leads to the time-picker.
            foreach ($nodes as $otherId => $other) {
                if ($otherId === $pick || $otherId === $booked) {
                    continue;
                }
                $nodes[$otherId] = $this->pointAt($other, (string) $id, $pick);
            }
            if ($flow['start'] === $id) {
                $flow['start'] = $pick;
            }
        }

        $flow['nodes'] = $nodes;

        return $flow;
    }

    /**
     * Send every way out of a step that pointed at `$from` to `$to` instead.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function pointAt(array $node, string $from, string $to): array
    {
        foreach (['next', 'otherwise', 'fallback'] as $exit) {
            if (($node[$exit] ?? null) === $from) {
                $node[$exit] = $to;
            }
        }

        if (isset($node['options']) && is_array($node['options'])) {
            $node['options'] = array_map(function (array $option) use ($from, $to): array {
                if (($option['next'] ?? null) === $from) {
                    $option['next'] = $to;
                }

                return $option;
            }, $node['options']);
        }

        return $node;
    }

    /**
     * @return list<array{key: string, name: string, description: string, category: string, flow: array{start: string, nodes: array<string, array<string, mixed>>}}>
     */
    private function all(): array
    {
        return [
            [
                'key' => 'msp_qualification',
                'category' => 'IT & managed services',
                'name' => 'Managed IT services (MSP)',
                'description' => 'The full managed-IT qualification: service interest, company size, current provider, biggest challenge, contact details and a meeting offer.',
                'flow' => $this->withInChatBooking(DefaultChatFlow::definition()),
            ],
            [
                'key' => 'cybersecurity',
                'category' => 'IT & managed services',
                'name' => 'Cybersecurity / MSSP',
                'description' => 'Focused on security buyers, with a high-priority route for anyone reporting a live incident.',
                'flow' => $this->withInChatBooking($this->cybersecurity()),
            ],
            [
                'key' => 'cmmc',
                'category' => 'IT & managed services',
                'name' => 'Compliance (CMMC readiness)',
                'description' => 'Qualifies defence-contract suppliers on CMMC level, timeline and contract exposure.',
                'flow' => $this->withInChatBooking($this->cmmc()),
            ],
            [
                'key' => 'cloud_m365',
                'category' => 'IT & managed services',
                'name' => 'Cloud & Microsoft 365',
                'description' => 'Migration, security, licensing and backup enquiries, sized by users and timeline, with a call offer.',
                'flow' => $this->withInChatBooking($this->cloudM365()),
            ],
            [
                'key' => 'voip',
                'category' => 'IT & managed services',
                'name' => 'VoIP & business phones',
                'description' => 'New systems, switching providers and Teams calling, sized by seats, ending in a quote or a call.',
                'flow' => $this->withInChatBooking($this->voip()),
            ],
            [
                'key' => 'saas_demo',
                'category' => 'Software & agencies',
                'name' => 'Software company: demo requests',
                'description' => 'Demo, pricing and feature questions (answered by AI), team size, and straight to booking a demo.',
                'flow' => $this->withInChatBooking($this->saasDemo()),
            ],
            [
                'key' => 'agency',
                'category' => 'Software & agencies',
                'name' => 'Marketing or web agency',
                'description' => 'The service they want and their budget, then contact details and a call offer.',
                'flow' => $this->withInChatBooking($this->agency()),
            ],
            [
                'key' => 'professional_services',
                'category' => 'Professional services',
                'name' => 'Accounting, legal or consulting firm',
                'description' => 'New enquiries by client type and urgency, booked into a first consultation; existing clients get a ticket.',
                'flow' => $this->withInChatBooking($this->professionalServices()),
            ],
            [
                'key' => 'healthcare',
                'category' => 'Professional services',
                'name' => 'Clinic or healthcare practice',
                'description' => 'Appointment call-back requests without asking for any medical details; billing questions become a ticket.',
                'flow' => $this->withInChatBooking($this->healthcare()),
            ],
            [
                'key' => 'real_estate',
                'category' => 'Local services',
                'name' => 'Real estate agency',
                'description' => 'Buying, selling, renting or a valuation, with the area and timeline, then a call with an agent.',
                'flow' => $this->withInChatBooking($this->realEstate()),
            ],
            [
                'key' => 'home_services',
                'category' => 'Local services',
                'name' => 'Home & trade services',
                'description' => 'Quote and booking requests by job type, timing and location, with a fast path for emergencies.',
                'flow' => $this->withInChatBooking($this->homeServices()),
            ],
            [
                'key' => 'ecommerce',
                'category' => 'Online store',
                'name' => 'Online store',
                'description' => 'Order and returns help becomes a ticket, product questions go to AI, wholesale enquiries become leads.',
                'flow' => $this->withInChatBooking($this->ecommerce()),
            ],
            [
                'key' => 'contact_capture',
                'category' => 'Any business',
                'name' => 'Simple contact form',
                'description' => 'Name, email, an optional phone number and their message. The fastest to set up.',
                'flow' => $this->withInChatBooking($this->contactCapture()),
            ],
            [
                'key' => 'consultation',
                'category' => 'Any business',
                'name' => 'Book a consultation',
                'description' => 'The short path: name, email, company, and straight to booking a call.',
                'flow' => $this->withInChatBooking($this->consultation()),
            ],
            [
                'key' => 'existing_customer',
                'category' => 'Any business',
                'name' => 'Existing customer support',
                'description' => 'Sends current clients to support, billing or their account manager instead of treating them as new leads.',
                'flow' => $this->withInChatBooking($this->existingCustomer()),
            ],
            [
                'key' => 'after_hours',
                'category' => 'Any business',
                'name' => 'After-hours capture',
                'description' => 'A short out-of-hours form that captures details and sets expectations for a reply.',
                'flow' => $this->withInChatBooking($this->afterHours()),
            ],
        ];
    }

    /**
     * Contact questions, worded once so every template asks the same way.
     *
     * @return array<string, mixed>
     */
    private function ask(string $field, string $text, string $next, bool $required = true): array
    {
        $input = ['email' => 'email', 'phone' => 'phone', 'company_name' => 'company'][$field] ?? 'text';

        return ['type' => 'input', 'input' => $input, 'field' => $field, 'text' => $text, 'optional' => ! $required, 'next' => $next];
    }

    /**
     * An existing customer's route: where to reply, what they need, then a ticket.
     *
     * @return array<string, array<string, mixed>>
     */
    private function supportPath(string $emailQuestion = 'What email address should we reply to?', string $issueQuestion = 'Briefly, what do you need help with?'): array
    {
        return [
            'in_support_email' => $this->ask('email', $emailQuestion, 'in_support_issue'),
            'in_support_issue' => ['type' => 'input', 'input' => 'text', 'field' => 'support_issue', 'text' => $issueQuestion, 'next' => 'end_support'],
            'end_support' => ['type' => 'end', 'outcome' => 'support', 'text' => 'Thanks, we have opened a support ticket and will reply by email.'],
        ];
    }

    /**
     * "Would you like a call?" and the two endings it leads to.
     *
     * @return array<string, array<string, mixed>>
     */
    private function meetingOffer(string $question, string $booked, string $later): array
    {
        return [
            'q_meeting' => [
                'type' => 'choice',
                'text' => $question,
                'field' => 'wants_meeting',
                'options' => [
                    ['id' => 'yes', 'label' => 'Yes, book it', 'score' => 30, 'next' => 'end_meeting'],
                    ['id' => 'no', 'label' => 'Not right now', 'score' => 0, 'next' => 'end_lead'],
                ],
            ],
            'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => $booked],
            'end_lead' => ['type' => 'end', 'outcome' => 'lead', 'text' => $later],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int}>  $answers  id, label, score
     * @return array<string, mixed>
     */
    private function question(string $text, string $field, array $answers, string $next): array
    {
        return [
            'type' => 'choice',
            'text' => $text,
            'field' => $field,
            'options' => array_map(fn (array $a) => ['id' => $a[0], 'label' => $a[1], 'score' => $a[2], 'next' => $next], $answers),
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function cloudM365(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Moving to the cloud, or getting more out of Microsoft 365?', 'next' => 'q_need'],
                'q_need' => [
                    'type' => 'choice',
                    'text' => 'What can we help with?',
                    'field' => 'cloud_need',
                    'options' => [
                        ['id' => 'migrate', 'label' => 'Move to Microsoft 365', 'score' => 20, 'next' => 'q_users'],
                        ['id' => 'secure', 'label' => 'Secure our Microsoft 365', 'score' => 20, 'next' => 'q_users'],
                        ['id' => 'licensing', 'label' => 'Licensing and costs', 'score' => 10, 'next' => 'q_users'],
                        ['id' => 'backup', 'label' => 'Backup and recovery', 'score' => 15, 'next' => 'q_users'],
                        ['id' => 'client', 'label' => 'I am an existing client', 'score' => 0, 'next' => 'in_support_email'],
                    ],
                ],
                'q_users' => $this->question('How many people use it?', 'user_count', [['1-10', '1–10', 0], ['11-50', '11–50', 10], ['51-250', '51–250', 20], ['250+', '250+', 15]], 'q_timeline'),
                'q_timeline' => $this->question('When would you like to get started?', 'timeline', [['asap', 'Right away', 25], ['quarter', 'Within 3 months', 15], ['exploring', 'Just exploring', 0]], 'in_name'),
                'in_name' => $this->ask('first_name', 'What is your first name?', 'in_email'),
                'in_email' => $this->ask('email', 'What is the best work email to reach you?', 'in_company'),
                'in_company' => $this->ask('company_name', 'What company are you with?', 'in_phone', false),
                'in_phone' => $this->ask('phone', 'And a phone number, if you would like a call?', 'q_meeting', false),
                ...$this->meetingOffer('Would you like a 20-minute call with a cloud specialist?', 'Great, pick a time for your call.', 'Thanks! A cloud specialist will be in touch shortly.'),
                ...$this->supportPath(),
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function voip(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Looking at a phone system for your business?', 'next' => 'q_need'],
                'q_need' => [
                    'type' => 'choice',
                    'text' => 'What are you after?',
                    'field' => 'phone_need',
                    'options' => [
                        ['id' => 'new', 'label' => 'A new phone system', 'score' => 20, 'next' => 'q_seats'],
                        ['id' => 'switch', 'label' => 'Switching providers', 'score' => 20, 'next' => 'q_seats'],
                        ['id' => 'teams', 'label' => 'Calling in Microsoft Teams', 'score' => 15, 'next' => 'q_seats'],
                        ['id' => 'client', 'label' => 'Help with our current service', 'score' => 0, 'next' => 'in_support_email'],
                    ],
                ],
                'q_seats' => $this->question('How many people need a phone line?', 'seats', [['1-10', '1–10', 5], ['11-50', '11–50', 15], ['51+', '51 or more', 25]], 'q_timeline'),
                'q_timeline' => $this->question('When do you need it working?', 'timeline', [['month', 'Within a month', 25], ['quarter', 'Within 3 months', 15], ['exploring', 'Just looking', 0]], 'in_name'),
                'in_name' => $this->ask('first_name', 'What is your first name?', 'in_email'),
                'in_email' => $this->ask('email', 'What email should we send your quote to?', 'in_company'),
                'in_company' => $this->ask('company_name', 'What company are you with?', 'in_phone', false),
                'in_phone' => $this->ask('phone', 'What is the best number to call you on?', 'q_meeting'),
                ...$this->meetingOffer('Would you like to talk it through on a quick call?', 'Great, pick a time that suits you.', 'Thanks! We will send your quote shortly.'),
                ...$this->supportPath(),
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function saasDemo(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Want to see how we can help your team?', 'next' => 'q_interest'],
                'q_interest' => [
                    'type' => 'choice',
                    'text' => 'What would you like to do?',
                    'field' => 'interest',
                    'options' => [
                        ['id' => 'demo', 'label' => 'Book a demo', 'score' => 30, 'next' => 'q_team'],
                        ['id' => 'pricing', 'label' => 'See pricing', 'score' => 20, 'next' => 'msg_pricing'],
                        ['id' => 'features', 'label' => 'Ask about features', 'score' => 10, 'next' => 'ai_ask'],
                        ['id' => 'client', 'label' => 'I am a customer and need help', 'score' => 0, 'next' => 'in_support_email'],
                    ],
                ],
                'msg_pricing' => [
                    'type' => 'message',
                    'text' => 'Our plans scale with your team. A short demo is the quickest way to get a price that fits.',
                    'next' => 'q_team',
                ],
                'ai_ask' => ['type' => 'ai', 'text' => 'What would you like to know about the product?', 'next' => 'q_demo', 'fallback' => null],
                'q_demo' => [
                    'type' => 'choice',
                    'text' => 'Would you like to see it in a live demo?',
                    'field' => 'wants_demo',
                    'options' => [
                        ['id' => 'yes', 'label' => 'Yes, show me', 'score' => 25, 'next' => 'q_team'],
                        ['id' => 'no', 'label' => 'Not right now', 'score' => 0, 'next' => 'end_browse'],
                    ],
                ],
                'end_browse' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'No problem. Come back any time.'],
                'q_team' => $this->question('How big is your team?', 'team_size', [['1-10', '1–10', 5], ['11-50', '11–50', 15], ['51-200', '51–200', 25], ['200+', '200+', 20]], 'in_name'),
                'in_name' => $this->ask('first_name', 'What is your first name?', 'in_email'),
                'in_email' => $this->ask('email', 'What is your work email?', 'in_company'),
                'in_company' => $this->ask('company_name', 'And your company?', 'end_demo'),
                'end_demo' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Great, pick a time for your demo.'],
                ...$this->supportPath(),
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function agency(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Looking to grow your business online?', 'next' => 'q_service'],
                'q_service' => $this->question('What are you most interested in?', 'service', [
                    ['seo', 'SEO', 15], ['ads', 'Paid ads', 15], ['website', 'A new website', 15], ['social', 'Social media', 10], ['unsure', 'Not sure yet', 5],
                ], 'q_budget'),
                'q_budget' => $this->question('Roughly what monthly budget do you have in mind?', 'budget', [
                    ['under_1k', 'Under $1,000', 0], ['1k_5k', '$1,000 – $5,000', 15], ['over_5k', 'Over $5,000', 30], ['unsure', 'Not sure yet', 5],
                ], 'in_name'),
                'in_name' => $this->ask('first_name', 'What is your first name?', 'in_email'),
                'in_email' => $this->ask('email', 'What is the best email to reach you?', 'in_website'),
                'in_website' => ['type' => 'input', 'input' => 'text', 'field' => 'website', 'text' => 'What is your website address, if you have one?', 'optional' => true, 'next' => 'in_company'],
                'in_company' => $this->ask('company_name', 'And your company name?', 'q_meeting', false),
                ...$this->meetingOffer('Would you like a free 30-minute strategy call?', 'Great, pick a time for your strategy call.', 'Thanks! We will send you some ideas shortly.'),
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function professionalServices(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! How can we help you today?', 'next' => 'q_need'],
                'q_need' => [
                    'type' => 'choice',
                    'text' => 'Which describes you best?',
                    'field' => 'enquiry_type',
                    'options' => [
                        ['id' => 'new', 'label' => 'I need advice on something new', 'score' => 15, 'next' => 'q_client'],
                        ['id' => 'client', 'label' => 'I am an existing client', 'score' => 0, 'next' => 'in_support_email'],
                    ],
                ],
                'q_client' => $this->question('Is this for you or for a business?', 'client_type', [['individual', 'For me', 5], ['business', 'For a business', 15]], 'q_urgency'),
                'q_urgency' => [
                    'type' => 'choice',
                    'text' => 'How soon do you need help?',
                    'field' => 'urgency',
                    'options' => [
                        ['id' => 'urgent', 'label' => 'It is urgent', 'score' => 25, 'next' => 'in_name', 'priority' => 'high'],
                        ['id' => 'weeks', 'label' => 'In the next few weeks', 'score' => 15, 'next' => 'in_name'],
                        ['id' => 'exploring', 'label' => 'Just exploring', 'score' => 0, 'next' => 'in_name'],
                    ],
                ],
                'in_name' => $this->ask('first_name', 'What is your first name?', 'in_email'),
                'in_email' => $this->ask('email', 'What is the best email to reach you?', 'in_phone'),
                'in_phone' => $this->ask('phone', 'And a phone number, if you prefer a call?', 'in_details', false),
                'in_details' => [
                    'type' => 'input', 'input' => 'text', 'field' => 'details', 'optional' => true, 'next' => 'end_meeting',
                    'text' => 'Anything we should know before we speak? Please do not include confidential details here.',
                ],
                'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Thank you. Pick a time for a first consultation.'],
                ...$this->supportPath(),
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function healthcare(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! How can we help? Please do not share medical details in this chat.', 'next' => 'q_need'],
                'q_need' => [
                    'type' => 'choice',
                    'text' => 'What would you like to do?',
                    'field' => 'visit_reason',
                    'options' => [
                        ['id' => 'new_patient', 'label' => 'Book a first appointment', 'score' => 20, 'next' => 'in_name'],
                        ['id' => 'existing_patient', 'label' => 'Book as an existing patient', 'score' => 10, 'next' => 'in_name'],
                        ['id' => 'billing', 'label' => 'A billing or insurance question', 'score' => 0, 'next' => 'in_support_email'],
                    ],
                ],
                'in_name' => $this->ask('first_name', 'What is your first name?', 'in_last_name'),
                'in_last_name' => $this->ask('last_name', 'And your last name?', 'in_phone'),
                'in_phone' => $this->ask('phone', 'What number should our front desk call you back on?', 'in_email'),
                'in_email' => $this->ask('email', 'And your email address?', 'q_time'),
                'q_time' => $this->question('When is best for a call back?', 'preferred_time', [['morning', 'Morning', 0], ['afternoon', 'Afternoon', 0], ['any', 'Any time', 0]], 'end_callback'),
                'end_callback' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks! Our front desk will call you to confirm a time.'],
                ...$this->supportPath('What email address should we reply to?', 'Briefly, what is your question? Please do not include medical details.'),
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function realEstate(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Buying, selling or renting?', 'next' => 'q_goal'],
                'q_goal' => $this->question('What are you looking to do?', 'goal', [
                    ['buy', 'Buy', 20], ['sell', 'Sell', 25], ['rent', 'Rent', 10], ['valuation', 'Get a free valuation', 25],
                ], 'in_area'),
                'in_area' => ['type' => 'input', 'input' => 'text', 'field' => 'area', 'text' => 'Which area are you interested in?', 'next' => 'q_timeline'],
                'q_timeline' => $this->question('When are you hoping to move?', 'timeline', [['asap', 'As soon as possible', 25], ['3_months', 'In the next 3 months', 15], ['later', 'Later than that', 5]], 'in_name'),
                'in_name' => $this->ask('first_name', 'What is your first name?', 'in_email'),
                'in_email' => $this->ask('email', 'What is the best email to reach you?', 'in_phone'),
                'in_phone' => $this->ask('phone', 'And your phone number?', 'q_meeting'),
                ...$this->meetingOffer('Would you like to book a call with one of our agents?', 'Great, pick a time to talk with an agent.', 'Thanks! An agent will be in touch shortly.'),
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function homeServices(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Need a quote or a booking?', 'next' => 'q_job'],
                'q_job' => [
                    'type' => 'choice',
                    'text' => 'What do you need?',
                    'field' => 'job_type',
                    'options' => [
                        ['id' => 'repair', 'label' => 'A repair', 'score' => 15, 'next' => 'q_when'],
                        ['id' => 'install', 'label' => 'A new installation', 'score' => 20, 'next' => 'q_when'],
                        ['id' => 'maintenance', 'label' => 'Regular maintenance', 'score' => 10, 'next' => 'q_when'],
                        ['id' => 'emergency', 'label' => 'It is an emergency', 'score' => 30, 'next' => 'msg_emergency', 'priority' => 'high'],
                    ],
                ],
                'msg_emergency' => ['type' => 'message', 'text' => 'Understood. Leave your details and we will call you straight back.', 'next' => 'in_name'],
                'q_when' => $this->question('When would you like it done?', 'timing', [['this_week', 'This week', 20], ['this_month', 'This month', 10], ['flexible', 'I am flexible', 5]], 'in_postcode'),
                'in_postcode' => ['type' => 'input', 'input' => 'text', 'field' => 'postcode', 'text' => 'What is your postcode or zip code?', 'next' => 'in_name'],
                'in_name' => $this->ask('first_name', 'What is your first name?', 'in_phone'),
                'in_phone' => $this->ask('phone', 'What is the best number to reach you on?', 'in_email'),
                'in_email' => $this->ask('email', 'And your email, for the quote?', 'in_details'),
                'in_details' => ['type' => 'input', 'input' => 'text', 'field' => 'job_details', 'text' => 'Briefly, describe the job.', 'optional' => true, 'next' => 'end_quote'],
                'end_quote' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks! We will be in touch with your quote shortly.'],
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function ecommerce(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! How can we help?', 'next' => 'q_need'],
                'q_need' => [
                    'type' => 'choice',
                    'text' => 'What do you need help with?',
                    'field' => 'shop_need',
                    'options' => [
                        ['id' => 'order', 'label' => 'Where is my order?', 'score' => 0, 'next' => 'in_support_email'],
                        ['id' => 'returns', 'label' => 'Returns and refunds', 'score' => 0, 'next' => 'in_support_email'],
                        ['id' => 'product', 'label' => 'A question about a product', 'score' => 10, 'next' => 'ai_ask'],
                        ['id' => 'wholesale', 'label' => 'Wholesale or bulk orders', 'score' => 25, 'next' => 'in_name'],
                    ],
                ],
                'ai_ask' => ['type' => 'ai', 'text' => 'What would you like to know?', 'next' => 'q_after', 'fallback' => null],
                'q_after' => [
                    'type' => 'choice',
                    'text' => 'Anything else?',
                    'field' => 'after_answer',
                    'options' => [
                        ['id' => 'person', 'label' => 'I would like to talk to someone', 'score' => 10, 'next' => 'in_name'],
                        ['id' => 'done', 'label' => 'That is all, thanks', 'score' => 0, 'next' => 'end_thanks'],
                    ],
                ],
                'end_thanks' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks for visiting, happy shopping!'],
                'in_name' => $this->ask('first_name', 'What is your first name?', 'in_email'),
                'in_email' => $this->ask('email', 'What is the best email to reach you?', 'in_company'),
                'in_company' => $this->ask('company_name', 'What company are you with, if any?', 'in_details', false),
                'in_details' => ['type' => 'input', 'input' => 'text', 'field' => 'order_details', 'text' => 'What are you looking for, and roughly how many?', 'next' => 'end_lead'],
                'end_lead' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks! Our team will get back to you shortly.'],
                ...$this->supportPath('What email address did you order with?', 'What is your order number, and how can we help?'),
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function contactCapture(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Leave your details and we will get back to you.', 'next' => 'in_name'],
                'in_name' => $this->ask('first_name', 'What is your name?', 'in_email'),
                'in_email' => $this->ask('email', 'What is the best email to reach you?', 'in_phone'),
                'in_phone' => $this->ask('phone', 'And a phone number, if you prefer a call?', 'in_message', false),
                'in_message' => ['type' => 'input', 'input' => 'text', 'field' => 'enquiry', 'text' => 'How can we help?', 'next' => 'end_lead'],
                'end_lead' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks! We will get back to you shortly.'],
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function cybersecurity(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Looking for help with cybersecurity?', 'next' => 'q_need'],
                'q_need' => [
                    'type' => 'choice',
                    'text' => 'What are you primarily looking for?',
                    'field' => 'security_need',
                    'options' => [
                        ['id' => 'assessment', 'label' => 'Security assessment', 'score' => 10, 'next' => 'q_size'],
                        ['id' => 'mdr', 'label' => 'MDR / SOC', 'score' => 15, 'next' => 'q_size'],
                        ['id' => 'compliance', 'label' => 'Compliance', 'score' => 10, 'next' => 'q_size'],
                        ['id' => 'insurance', 'label' => 'Cyber insurance requirements', 'score' => 10, 'next' => 'q_size'],
                        ['id' => 'incident', 'label' => 'We may have a security incident', 'score' => 30, 'next' => 'msg_incident', 'priority' => 'high'],
                        ['id' => 'general', 'label' => 'General cybersecurity help', 'score' => 5, 'next' => 'q_size'],
                    ],
                ],
                'msg_incident' => [
                    'type' => 'message',
                    'text' => 'Understood — we treat active incidents as a priority. Leave your details and our security team will contact you immediately. Please do not share any technical details here.',
                    'next' => 'in_name',
                ],
                'q_size' => [
                    'type' => 'choice',
                    'text' => 'How many employees do you have?',
                    'field' => 'company_size',
                    'options' => [
                        ['id' => '1-10', 'label' => '1–10', 'score' => 0, 'next' => 'q_timeline'],
                        ['id' => '11-50', 'label' => '11–50', 'score' => 10, 'next' => 'q_timeline'],
                        ['id' => '51-250', 'label' => '51–250', 'score' => 20, 'next' => 'q_timeline'],
                        ['id' => '250+', 'label' => '250+', 'score' => 15, 'next' => 'q_timeline'],
                    ],
                ],
                'q_timeline' => [
                    'type' => 'choice',
                    'text' => 'What is your timeline?',
                    'field' => 'timeline',
                    'options' => [
                        ['id' => 'urgent', 'label' => 'Immediately', 'score' => 25, 'next' => 'in_name'],
                        ['id' => '90days', 'label' => 'Within 90 days', 'score' => 20, 'next' => 'in_name'],
                        ['id' => 'exploring', 'label' => 'Just exploring', 'score' => 0, 'next' => 'in_name'],
                    ],
                ],
                'in_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'What is your first name?', 'next' => 'in_email'],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'What is the best work email to reach you?', 'next' => 'in_company'],
                'in_company' => ['type' => 'input', 'input' => 'company', 'field' => 'company_name', 'text' => 'What company are you with?', 'next' => 'q_meeting'],
                'q_meeting' => [
                    'type' => 'choice',
                    'text' => 'Would you like a 30-minute security consultation?',
                    'field' => 'wants_meeting',
                    'options' => [
                        ['id' => 'yes', 'label' => 'Yes — schedule it', 'score' => 30, 'next' => 'end_meeting'],
                        ['id' => 'no', 'label' => 'Not right now', 'score' => 0, 'next' => 'end_thanks'],
                    ],
                ],
                'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Perfect — pick a time that suits you.'],
                'end_thanks' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks! Our security team will be in touch shortly.'],
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function cmmc(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Working towards CMMC compliance? Let us find out where you stand.', 'next' => 'q_level'],
                'q_level' => [
                    'type' => 'choice',
                    'text' => 'Which CMMC level do you need to meet?',
                    'field' => 'cmmc_level',
                    'options' => [
                        ['id' => 'level1', 'label' => 'Level 1', 'score' => 10, 'next' => 'q_contract'],
                        ['id' => 'level2', 'label' => 'Level 2', 'score' => 20, 'next' => 'q_contract'],
                        ['id' => 'unsure', 'label' => 'Not sure yet', 'score' => 10, 'next' => 'q_contract'],
                    ],
                ],
                'q_contract' => [
                    'type' => 'choice',
                    'text' => 'Do you currently hold or bid on DoD contracts?',
                    'field' => 'dod_contracts',
                    'options' => [
                        ['id' => 'hold', 'label' => 'We hold contracts', 'score' => 25, 'next' => 'q_timeline'],
                        ['id' => 'bidding', 'label' => 'We are bidding', 'score' => 20, 'next' => 'q_timeline'],
                        ['id' => 'exploring', 'label' => 'Exploring the market', 'score' => 5, 'next' => 'q_timeline'],
                    ],
                ],
                'q_timeline' => [
                    'type' => 'choice',
                    'text' => 'When do you need to be compliant?',
                    'field' => 'timeline',
                    'options' => [
                        ['id' => '90days', 'label' => 'Within 90 days', 'score' => 25, 'next' => 'in_name'],
                        ['id' => '6months', 'label' => 'Within 6 months', 'score' => 15, 'next' => 'in_name'],
                        ['id' => 'later', 'label' => 'Later than that', 'score' => 5, 'next' => 'in_name'],
                    ],
                ],
                'in_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'What is your first name?', 'next' => 'in_email'],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'And your work email?', 'next' => 'in_company'],
                'in_company' => ['type' => 'input', 'input' => 'company', 'field' => 'company_name', 'text' => 'What company are you with?', 'next' => 'end_meeting'],
                'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Thanks — book a CMMC readiness call and we will map out your gaps.'],
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function consultation(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Happy to set up a quick call — just three details.', 'next' => 'in_name'],
                'in_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'What is your name?', 'next' => 'in_email'],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'What is your work email?', 'next' => 'in_company'],
                'in_company' => ['type' => 'input', 'input' => 'company', 'field' => 'company_name', 'text' => 'And your company?', 'next' => 'score_intent'],
                'score_intent' => ['type' => 'score', 'points' => 25, 'next' => 'end_meeting'],
                'end_meeting' => ['type' => 'end', 'outcome' => 'meeting', 'text' => 'Great — choose a time that works for you.'],
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function existingCustomer(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => ['type' => 'message', 'text' => 'Hi! Are you an existing client?', 'next' => 'q_who'],
                'q_who' => [
                    'type' => 'choice',
                    'text' => 'How can we help today?',
                    'field' => 'visitor_type',
                    'options' => [
                        ['id' => 'support', 'label' => 'Technical support', 'score' => 0, 'next' => 'in_support_email'],
                        ['id' => 'billing', 'label' => 'Billing question', 'score' => 0, 'next' => 'in_support_email'],
                        ['id' => 'account', 'label' => 'Speak to my account manager', 'score' => 0, 'next' => 'in_support_email'],
                        ['id' => 'project', 'label' => 'Project question', 'score' => 0, 'next' => 'in_support_email'],
                        ['id' => 'new', 'label' => 'I am not a client yet', 'score' => 10, 'next' => 'in_name'],
                    ],
                ],
                'in_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'Welcome! What is your name?', 'next' => 'in_email'],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'And the best email to reach you?', 'next' => 'end_lead'],
                'in_support_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'What email address should we reply to?', 'next' => 'in_support_issue'],
                'in_support_issue' => ['type' => 'input', 'input' => 'text', 'field' => 'support_issue', 'text' => 'Briefly, what do you need help with?', 'next' => 'end_support'],
                'end_support' => ['type' => 'end', 'outcome' => 'support', 'text' => 'Thanks — we have opened a support ticket and our team will follow up by email.'],
                'end_lead' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Thanks! Someone will reach out shortly.'],
            ],
        ];
    }

    /** @return array{start: string, nodes: array<string, array<string, mixed>>} */
    private function afterHours(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => [
                    'type' => 'message',
                    'text' => 'Thanks for getting in touch. Our team is offline right now, but leave your details and we will reply next business day.',
                    'next' => 'in_name',
                ],
                'in_name' => ['type' => 'input', 'input' => 'text', 'field' => 'first_name', 'text' => 'What is your name?', 'next' => 'in_email'],
                'in_email' => ['type' => 'input', 'input' => 'email', 'field' => 'email', 'text' => 'What email should we reply to?', 'next' => 'in_message'],
                'in_message' => ['type' => 'input', 'input' => 'text', 'field' => 'enquiry', 'text' => 'Briefly, what do you need help with?', 'next' => 'end_thanks'],
                'end_thanks' => ['type' => 'end', 'outcome' => 'lead', 'text' => 'Got it — we will be in touch first thing next business day.'],
            ],
        ];
    }
}
