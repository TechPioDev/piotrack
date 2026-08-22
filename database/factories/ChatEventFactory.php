<?php

namespace Database\Factories;

use App\Models\ChatEvent;
use App\Models\ChatWidget;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatEvent>
 */
class ChatEventFactory extends Factory
{
    protected $model = ChatEvent::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => fake()->randomElement(ChatEvent::TYPES),
            'node_id' => null,
            'meta' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ChatEvent $event) {
            if (! $event->chat_widget_id) {
                $event->chat_widget_id = ChatWidget::factory()
                    ->create(['organization_id' => $event->organization_id])
                    ->id;
            }
        });
    }
}
