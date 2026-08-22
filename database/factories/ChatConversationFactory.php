<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    protected $model = ChatConversation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'status' => 'new',
            'lead_score' => 0,
        ];
    }

    public function configure(): static
    {
        // Give the conversation a widget in its OWN organization, so the whole graph
        // stays within one tenant.
        return $this->afterMaking(function (ChatConversation $conversation) {
            if (! $conversation->chat_widget_id) {
                $conversation->chat_widget_id = ChatWidget::factory()
                    ->create(['organization_id' => $conversation->organization_id])
                    ->id;
            }
        });
    }
}
