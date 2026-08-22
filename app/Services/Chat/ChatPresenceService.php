<?php

namespace App\Services\Chat;

use App\Models\ChatAgentPresence;
use App\Models\ChatConversation;
use App\Models\Organization;
use App\Models\User;

/**
 * Who is available to take a live chat right now.
 *
 * Presence is explicit (an agent picks Online/Away/Busy/Offline) but verified by
 * a heartbeat, because a closed browser never says goodbye. An agent who stops
 * checking in is treated as away, so a visitor is never handed to nobody.
 */
class ChatPresenceService
{
    /** Set an agent's own status and refresh their heartbeat. */
    public function setStatus(User $user, string $status): ChatAgentPresence
    {
        $presence = ChatAgentPresence::firstOrNew(['user_id' => $user->id]);
        $presence->status = in_array($status, ChatAgentPresence::STATUSES, true) ? $status : 'offline';
        $presence->last_seen_at = now();
        $presence->save();

        return $presence;
    }

    /** Keep an already-online agent alive without changing their chosen status. */
    public function heartbeat(User $user): void
    {
        ChatAgentPresence::where('user_id', $user->id)->update(['last_seen_at' => now()]);
    }

    public function statusFor(User $user): string
    {
        return ChatAgentPresence::where('user_id', $user->id)->first()?->effectiveStatus() ?? 'offline';
    }

    /**
     * Agents who could take a chat this moment.
     *
     * @return list<ChatAgentPresence>
     */
    public function available(): array
    {
        return ChatAgentPresence::query()
            ->where('status', 'online')
            ->where('last_seen_at', '>', now()->subMinutes(ChatAgentPresence::STALE_AFTER_MINUTES))
            ->with('user:id,name')
            ->get()
            ->all();
    }

    public function anyoneAvailable(): bool
    {
        return $this->available() !== [];
    }

    /**
     * Pick the agent to hand a conversation to: the one already assigned if they
     * are around, otherwise the available agent handling the fewest open live
     * chats, so the load spreads instead of always landing on the same person.
     */
    public function pickAgent(ChatConversation $conversation): ?User
    {
        $available = $this->available();
        if ($available === []) {
            return null;
        }

        foreach ($available as $presence) {
            if ($presence->user_id === $conversation->assignee_id) {
                return $presence->user;
            }
        }

        $loads = ChatConversation::query()
            ->where('is_live', true)
            ->whereIn('status', ['open', 'waiting', 'assigned'])
            ->selectRaw('assignee_id, COUNT(*) as total')
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id')
            ->all();

        usort($available, fn (ChatAgentPresence $a, ChatAgentPresence $b) => ($loads[$a->user_id] ?? 0) <=> ($loads[$b->user_id] ?? 0));

        return $available[0]->user;
    }

    /**
     * Presence for everyone in the tenant, for the inbox roster.
     *
     * @return list<array{id: int, name: string, status: string}>
     */
    public function roster(Organization $organization): array
    {
        $presence = ChatAgentPresence::query()->get()->keyBy('user_id');

        return $organization->members()
            ->wherePivot('status', 'active')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'status' => $presence->get($user->id)?->effectiveStatus() ?? 'offline',
            ])
            ->values()
            ->all();
    }
}
