<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\Notifications\OrgChannelNotifier;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Central fan-out for platform notifications. Keeps recipient targeting in one
 * place so services just say "notify this organization's owners".
 */
class NotificationDispatcher
{
    public function __construct(private OrgChannelNotifier $orgChannels) {}

    public function toUser(User $user, Notification $notification): void
    {
        $user->notify($notification);
    }

    public function toOrganizationOwners(Organization $organization, Notification $notification): void
    {
        $owners = $organization->owners()->get();

        if ($owners->isNotEmpty()) {
            NotificationFacade::send($owners, $notification);
        }

        // NOTIF-004/005: the org's Slack/Teams/webhook channels get one copy
        // per EVENT, regardless of how many owners were notified.
        if ($notification instanceof PlatformNotification) {
            $this->orgChannels->send($organization, $notification);
        }
    }
}
