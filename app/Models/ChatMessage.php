<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chat_session_id
 * @property int $user_id
 * @property string $role
 * @property string $content
 * @property string|null $model
 * @property int|null $tokens_used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'role', 'content', 'model', 'tokens_used'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;
}
