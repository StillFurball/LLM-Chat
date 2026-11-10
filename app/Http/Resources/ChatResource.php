<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The assistant's reply to the prompt sent in the current request.
 *
 * @mixin ChatMessage
 */
class ChatResource extends JsonResource
{
    /**
     * The API contract fixes its own envelope, so the default "data" wrapper is disabled.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array{status: string, data: array{session_id: int, model_used: string|null, prompt: string, ai_response: string, timestamp: string}}
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => 'success',
            'data' => [
                'session_id' => $this->chat_session_id,
                'model_used' => $this->model,
                'prompt' => $request->input('prompt'),
                'ai_response' => $this->content,
                'timestamp' => $this->created_at->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
