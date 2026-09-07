<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/notifications', [
            'notifications' => $user->notifications()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'category' => $n->data['category'] ?? null,
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? '',
                    'url' => $n->data['url'] ?? null,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ]),
            'preferences' => $this->preferenceMatrix($user),
            'categories' => NotificationPreference::CATEGORIES,
            'channels' => NotificationPreference::CHANNELS,
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->where('id', $id)->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(NotificationPreference::CATEGORIES)],
            'channel' => ['required', Rule::in(NotificationPreference::CHANNELS)],
            'enabled' => ['required', 'boolean'],
        ]);

        // Security notices cannot be disabled (SMS stays opt-in everywhere).
        if ($validated['category'] === 'security' && $validated['channel'] !== 'sms') {
            return back();
        }

        $request->user()->notificationPreferences()->updateOrCreate(
            ['category' => $validated['category'], 'channel' => $validated['channel']],
            ['enabled' => $validated['enabled']],
        );

        return back();
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function preferenceMatrix(User $user): array
    {
        $existing = $user->notificationPreferences->keyBy(fn ($p) => $p->category.':'.$p->channel);
        $matrix = [];

        foreach (NotificationPreference::CATEGORIES as $category) {
            foreach (NotificationPreference::CHANNELS as $channel) {
                $pref = $existing->get($category.':'.$channel);
                // SMS is opt-in (NOTIF-003): off unless explicitly enabled.
                $default = $channel !== 'sms';
                $matrix[$category][$channel] = $pref === null ? $default : (bool) $pref->enabled;
            }
        }

        return $matrix;
    }
}
