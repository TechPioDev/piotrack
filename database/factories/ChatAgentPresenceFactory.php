<?php

namespace Database\Factories;

use App\Models\ChatAgentPresence;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatAgentPresence>
 */
class ChatAgentPresenceFactory extends Factory
{
    protected $model = ChatAgentPresence::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'status' => 'online',
            'last_seen_at' => now(),
        ];
    }

    public function offline(): static
    {
        return $this->state(fn () => ['status' => 'offline', 'last_seen_at' => null]);
    }
}
