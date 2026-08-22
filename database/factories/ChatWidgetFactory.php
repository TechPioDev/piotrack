<?php

namespace Database\Factories;

use App\Models\ChatWidget;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatWidget>
 */
class ChatWidgetFactory extends Factory
{
    protected $model = ChatWidget::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company().' Chat',
            'description' => fake()->sentence(),
            'status' => 'active',
            'flow' => null,
            'theme' => ['accent' => '#0bb39e', 'position' => 'bottom-right', 'title' => 'Chat with us'],
            'consent' => ['required' => false],
            'settings' => ['language' => 'en', 'mode' => 'bot'],
            'allowed_domains' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => 'paused']);
    }
}
