<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatAiRequest;
use App\Http\Resources\ChatResource;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\LlmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatAiController extends Controller
{
    private const string UNAVAILABLE_MESSAGE = 'Layanan AI sedang tidak tersedia. Silakan coba lagi nanti.';

    public function __construct(private LlmClient $llm) {}

    /**
     * Answer a prompt with one complete JSON reply.
     */
    public function store(ChatAiRequest $request): ChatResource|JsonResponse
    {
        $session = $this->recordPrompt($request);

        try {
            $reply = $this->llm->chat($session->history());
        } catch (Throwable $exception) {
            Log::error('Semua penyedia LLM gagal.', ['session_id' => $session->id, 'exception' => $exception]);

            return response()->json(['status' => 'error', 'message' => self::UNAVAILABLE_MESSAGE], 503);
        }

        return new ChatResource($this->recordReply($session, $reply));
    }

    /**
     * Answer a prompt as Server-Sent Events, one event per piece of the reply.
     */
    public function stream(ChatAiRequest $request): StreamedResponse
    {
        $session = $this->recordPrompt($request);

        return response()->stream(function () use ($session): void {
            try {
                $reply = $this->llm->stream(
                    $session->history(),
                    fn (string $chunk) => $this->sendEvent(['content' => $chunk]),
                );
            } catch (Throwable $exception) {
                Log::error('Semua penyedia LLM gagal.', ['session_id' => $session->id, 'exception' => $exception]);

                $this->sendEvent(['status' => 'error', 'message' => self::UNAVAILABLE_MESSAGE], 'error');

                return;
            }

            $message = $this->recordReply($session, $reply);

            $this->sendEvent(['done' => true, 'session_id' => $session->id, 'model_used' => $message->model]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Store the user's prompt in the requested session, or in a new one.
     */
    private function recordPrompt(ChatAiRequest $request): ChatSession
    {
        $user = $request->user();
        $prompt = $request->validated('prompt');

        $session = $request->filled('session_id')
            ? $user->chatSessions()->findOrFail($request->integer('session_id'))
            : $user->chatSessions()->create(['title' => Str::limit($prompt, 50)]);

        $session->messages()->create(['user_id' => $user->id, 'role' => 'user', 'content' => $prompt]);

        return $session;
    }

    /**
     * @param  array{content: string, model: string, tokens: int|null}  $reply
     */
    private function recordReply(ChatSession $session, array $reply): ChatMessage
    {
        return $session->messages()->create([
            'user_id' => $session->user_id,
            'role' => 'assistant',
            'content' => $reply['content'],
            'model' => $reply['model'],
            'tokens_used' => $reply['tokens'],
        ]);
    }

    /**
     * Write one Server-Sent Event; the JSON payload keeps newlines in the text from breaking the event framing.
     *
     * @param  array<string, mixed>  $data
     */
    private function sendEvent(array $data, ?string $event = null): void
    {
        echo ($event ? "event: {$event}\n" : '').'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
