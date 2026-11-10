<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_session_id' => ChatSession::factory(),
            'user_id' => fn (array $attributes) => ChatSession::whereKey($attributes['chat_session_id'])->valueOrFail('user_id'),
            'role' => 'user',
            'content' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the message is a reply from the LLM.
     */
    public function assistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'assistant',
            'model' => 'qwen3:14b',
            'tokens_used' => fake()->numberBetween(10, 500),
        ]);
    }
}
