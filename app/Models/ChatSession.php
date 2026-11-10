<?php

namespace App\Models;

use Database\Factories\ChatSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title'])]
class ChatSession extends Model
{
    /** @use HasFactory<ChatSessionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * The conversation so far, in the shape LlmClient expects.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function history(): array
    {
        // shortcut: sends the whole session, keep only the latest messages once sessions outgrow the model's context window.
        return $this->messages()->oldest('id')->get()
            ->map(fn (ChatMessage $message) => ['role' => $message->role, 'content' => $message->content])
            ->all();
    }
}
