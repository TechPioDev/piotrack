<?php

namespace App\Jobs;

use App\Models\ChatConversation;
use App\Models\Organization;
use App\Services\Chat\ChatReplyMailer;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a couple of minutes after an agent replies: by then a visitor still in
 * the chat has seen the reply, and one who left gets it by email instead.
 * Several quick replies are sent together, and a repeat run sends nothing new.
 */
class EmailChatReplies implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $conversationId, public int $organizationId) {}

    public function handle(ChatReplyMailer $mailer, CurrentOrganization $current): void
    {
        $organization = Organization::find($this->organizationId);
        if ($organization === null) {
            return;
        }

        $current->set($organization);

        $conversation = ChatConversation::find($this->conversationId);
        if ($conversation !== null) {
            $mailer->sendPending($conversation);
        }
    }
}
