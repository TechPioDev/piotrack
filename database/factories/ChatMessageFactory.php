<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'role' => 'visitor',
            'body' => fake()->sentence(),
            'meta' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ChatMessage $message) {
            if (! $message->chat_conversation_id) {
                $message->chat_conversation_id = ChatConversation::factory()
                    ->create(['organization_id' => $message->organization_id])
                    ->id;
            }
        });
    }
}
