<?php

namespace App\Services\Chat;

/**
 * The starter MSP qualification flow a new widget begins with (spec §11–13).
 * Tenants customise it per widget; the flow builder (Phase 2) edits this same
 * structure. The graph is a map of nodes; every choice option carries its own
 * score contribution and next pointer, which is how conditional branching works.
 *
 * Node types (Phase 1): message | choice | input | consent | end.
 * Input kinds: text | email | phone | number | company.
 */
class DefaultChatFlow
{
    /**
     * @return array{start: string, nodes: array<string, array<string, mixed>>}
     */
    public static function definition(): array
    {
        return [
            'start' => 'welcome',
            'nodes' => [
                'welcome' => [
                    'type' => 'message',
                    'text' => 'Hi! Looking for help with your business technology?',
                    'next' => 'q_service',
                ],
                'q_service' => [
                    'type' => 'choice',
                    'text' => 'What can we help you with?',
                    'field' => 'service',
                    'options' => [
                        ['id' => 'managed_it', 'label' => 'Managed IT Services', 'score' => 10, 'next' => 'q_size'],
                        ['id' => 'cybersecurity', 'label' => 'Cybersecurity', 'score' => 15, 'next' => 'q_cyber_need'],
                        ['id' => 'co_managed', 'label' => 'Co-Managed IT', 'score' => 10, 'next' => 'q_size'],
                        ['id' => 'm365', 'label' => 'Microsoft 365', 'score' => 5, 'next' => 'q_size'],
                        ['id' => 'cmmc', 'label' => 'CMMC / Compliance', 'score' => 15, 'next' => 'q_size'],
                        ['id' => 'existing', 'label' => 'Existing Customer Support', 'score' => 0, 'next' => 'q_support'],
                        ['id' => 'other', 'label' => 'Something Else', 'score' => 0, 'next' => 'q_size'],
                    ],
                ],

                // ---- Cybersecurity branch (§12) ----
                'q_cyber_need' => [
                    'type' => 'choice',
                    'field' => 'security_need',
                    'text' => 'What are you primarily looking for?',
                    'options' => [
                        ['id' => 'assessment', 'label' => 'Security assessment', 'score' => 10, 'next' => 'q_size'],
                        ['id' => 'mdr', 'label' => 'MDR / SOC', 'score' => 15, 'next' => 'q_size'],
                        ['id' => 'compliance', 'label' => 'Compliance', 'score' => 10, 'next' => 'q_size'],
                        ['id' => 'cmmc', 'label' => 'CMMC', 'score' => 15, 'next' => 'q_size'],
                        ['id' => 'insurance', 'label' => 'Cyber insurance requirements', 'score' => 10, 'next' => 'q_size'],
                        // A live incident is routed as high priority; the bot never
                        // attempts remediation advice for anonymous visitors.
                        ['id' => 'incident', 'label' => 'Security incident', 'score' => 30, 'next' => 'msg_incident', 'priority' => 'high'],
                        ['id' => 'general', 'label' => 'General cybersecurity help', 'score' => 5, 'next' => 'q_size'],
                    ],
                ],
                'msg_incident' => [
                    'type' => 'message',
                    'text' => 'Understood — we treat active incidents as a priority. Leave your details and our security team will contact you immediately.',
                    'next' => 'in_first_name',
                ],

                // ---- Qualification (§11) ----
                'q_size' => [
                    'type' => 'choice',
                    'text' => 'Approximately how many employees do you have?',
                    'field' => 'company_size',
                    'options' => [
                        ['id' => '1-10', 'label' => '1–10', 'score' => 0, 'next' => 'q_provider'],
                        ['id' => '11-50', 'label' => '11–50', 'score' => 10, 'next' => 'q_provider'],
                        ['id' => '51-250', 'label' => '51–250', 'score' => 20, 'next' => 'q_provider'],
                        ['id' => '250+', 'label' => '250+', 'score' => 15, 'next' => 'q_provider'],
                    ],
                ],
                'q_provider' => [
                    'type' => 'choice',
                    'text' => 'Are you currently working with another IT provider?',
                    'field' => 'current_provider',
                    'options' => [
                        ['id' => 'yes', 'label' => 'Yes', 'score' => 15, 'next' => 'q_challenge'],
                        ['id' => 'no', 'label' => 'No', 'score' => 5, 'next' => 'q_challenge'],
                    ],
                ],
                'q_challenge' => [
                    'type' => 'choice',
                    'text' => 'What is your biggest IT challenge right now?',
                    'field' => 'challenge',
                    'options' => [
                        ['id' => 'slow_support', 'label' => 'Slow support', 'score' => 5, 'next' => 'in_first_name'],
                        ['id' => 'security', 'label' => 'Cybersecurity', 'score' => 10, 'next' => 'in_first_name'],
                        ['id' => 'm365', 'label' => 'Microsoft 365', 'score' => 5, 'next' => 'in_first_name'],
                        ['id' => 'backup', 'label' => 'Backup & recovery', 'score' => 5, 'next' => 'in_first_name'],
                        ['id' => 'compliance', 'label' => 'Compliance', 'score' => 10, 'next' => 'in_first_name'],
                        ['id' => 'strategy', 'label' => 'IT strategy', 'score' => 5, 'next' => 'in_first_name'],
                        ['id' => 'other', 'label' => 'Other', 'score' => 0, 'next' => 'in_first_name'],
                    ],
                ],

                // ---- Contact capture (§16) ----
                'in_first_name' => [
                    'type' => 'input',
                    'input' => 'text',
                    'field' => 'first_name',
                    'text' => 'Great — let me get a few details. What is your first name?',
                    'next' => 'in_last_name',
                ],
                'in_last_name' => [
                    'type' => 'input',
                    'input' => 'text',
                    'field' => 'last_name',
                    'text' => 'And your last name?',
                    'next' => 'in_email',
                ],
                'in_email' => [
                    'type' => 'input',
                    'input' => 'email',
                    'field' => 'email',
                    'text' => 'What is the best work email to reach you?',
                    'next' => 'in_phone',
                ],
                'in_phone' => [
                    'type' => 'input',
                    'input' => 'phone',
                    'field' => 'phone',
                    'text' => 'A phone number, in case email is slow?',
                    'optional' => true,
                    'next' => 'in_company',
                ],
                'in_company' => [
                    'type' => 'input',
                    'input' => 'company',
                    'field' => 'company_name',
                    'text' => 'What company are you with?',
                    'next' => 'q_timeframe',
                ],

                // ---- Deeper qualification, deliberately AFTER contact capture ----
                //
                // Drop-off is measured per question, and it is brutal before the
                // email: the phone step alone loses about four visitors in ten.
                // Everything below is asked once the lead already exists, so an
                // abandonment here costs detail rather than the lead itself, and
                // a sales conversation still starts. Score is weighted by how
                // much each answer changes whether this is worth a call today.
                'q_timeframe' => [
                    'type' => 'choice',
                    'field' => 'timeframe',
                    'text' => 'When are you looking to make a change?',
                    'options' => [
                        ['id' => 'now', 'label' => 'Right away', 'score' => 25, 'next' => 'q_locations', 'priority' => 'high'],
                        ['id' => '1_3_months', 'label' => 'In the next 1–3 months', 'score' => 15, 'next' => 'q_locations'],
                        ['id' => '3_6_months', 'label' => '3–6 months', 'score' => 5, 'next' => 'q_locations'],
                        ['id' => 'researching', 'label' => 'Just researching for now', 'score' => 0, 'next' => 'q_locations'],
                    ],
                ],
                'q_locations' => [
                    'type' => 'choice',
                    'field' => 'locations',
                    'text' => 'How many sites would we be supporting?',
                    'options' => [
                        ['id' => '1', 'label' => 'One', 'score' => 0, 'next' => 'q_compliance'],
                        ['id' => '2-5', 'label' => 'Two to five', 'score' => 10, 'next' => 'q_compliance'],
                        ['id' => '6+', 'label' => 'Six or more', 'score' => 20, 'next' => 'q_compliance'],
                        ['id' => 'remote', 'label' => 'Mostly remote staff', 'score' => 10, 'next' => 'q_compliance'],
                    ],
                ],
                'q_compliance' => [
                    'type' => 'choice',
                    'field' => 'compliance',
                    'text' => 'Do you have any compliance requirements?',
                    'options' => [
                        ['id' => 'hipaa', 'label' => 'HIPAA', 'score' => 20, 'next' => 'q_meeting'],
                        ['id' => 'cmmc', 'label' => 'CMMC / DFARS', 'score' => 25, 'next' => 'q_meeting'],
                        ['id' => 'pci', 'label' => 'PCI DSS', 'score' => 15, 'next' => 'q_meeting'],
                        ['id' => 'soc2', 'label' => 'SOC 2', 'score' => 20, 'next' => 'q_meeting'],
                        ['id' => 'none', 'label' => 'None that I know of', 'score' => 0, 'next' => 'q_meeting'],
                        ['id' => 'unsure', 'label' => 'Not sure', 'score' => 5, 'next' => 'q_meeting'],
                    ],
                ],

                // ---- Meeting offer (§22, Phase 1: link to the booking page) ----
                'q_meeting' => [
                    'type' => 'choice',
                    'text' => 'Would you like to schedule a 30-minute consultation?',
                    'field' => 'wants_meeting',
                    'options' => [
                        ['id' => 'yes', 'label' => 'Yes — schedule it', 'score' => 30, 'next' => 'end_meeting'],
                        ['id' => 'no', 'label' => 'Not right now', 'score' => 0, 'next' => 'end_thanks'],
                    ],
                ],
                'end_meeting' => [
                    'type' => 'end',
                    'outcome' => 'meeting',
                    'text' => 'Perfect — pick a time that suits you and we will confirm by email.',
                ],
                'end_thanks' => [
                    'type' => 'end',
                    'outcome' => 'lead',
                    'text' => 'Thanks! Our team will reach out shortly. Have a great day.',
                ],

                // ---- Existing customer routing (§13): support, never a sales lead ----
                'q_support' => [
                    'type' => 'choice',
                    'text' => 'Happy to help. What do you need?',
                    'field' => 'support_topic',
                    'options' => [
                        ['id' => 'technical', 'label' => 'Technical Support', 'score' => 0, 'next' => 'end_support'],
                        ['id' => 'billing', 'label' => 'Billing', 'score' => 0, 'next' => 'end_support'],
                        ['id' => 'account', 'label' => 'Account Manager', 'score' => 0, 'next' => 'end_support'],
                        ['id' => 'project', 'label' => 'Project Question', 'score' => 0, 'next' => 'end_support'],
                        ['id' => 'other', 'label' => 'Other', 'score' => 0, 'next' => 'end_support'],
                    ],
                ],
                'end_support' => [
                    'type' => 'end',
                    'outcome' => 'support',
                    'text' => 'Thanks — our support team has been notified and will follow up through your usual support channel.',
                ],
            ],
        ];
    }
}
