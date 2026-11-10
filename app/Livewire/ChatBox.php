<?php

namespace App\Livewire;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\LlmClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class ChatBox extends Component
{
    private const int VISIBLE_MESSAGES = 20;

    /**
     * Comes from the URL and the browser, so every action re-checks that the session belongs to the user.
     */
    #[Url(as: 'sesi')]
    public ?int $sessionId = null;

    public string $prompt = '';

    /**
     * The latest messages of the open session, oldest first.
     *
     * @var array<int, array{id: int, role: string, content: string}>
     */
    #[Locked]
    public array $visibleMessages = [];

    public ?string $notice = null;

    public function mount(): void
    {
        $this->loadMessages();
    }

    /**
     * Open one of the user's sessions, or an empty new chat when no id is given.
     */
    #[On('session-selected')]
    public function openSession(?int $sessionId = null): void
    {
        $this->sessionId = $sessionId;
        $this->reset('prompt', 'notice');
        $this->resetValidation();

        $this->loadMessages();
    }

    /**
     * Store the prompt, stream the LLM's reply to the browser, then store the reply.
     */
    public function kirim(LlmClient $llm): void
    {
        $this->validate();

        $session = $this->sessionId === null ? $this->createSession() : $this->ownedSession();
        $session->messages()->create(['user_id' => $session->user_id, 'role' => 'user', 'content' => trim($this->prompt)]);

        $this->reset('prompt', 'notice');

        $answer = '';

        try {
            // Clears whatever an earlier reply left in the streaming bubble.
            $this->stream(to: 'answer', content: '', replace: true);

            $reply = $llm->stream($session->history(), function (string $chunk) use (&$answer): void {
                $answer .= $chunk;

                // Streamed content is inserted as HTML, so the model's text must be escaped.
                $this->stream(to: 'answer', content: e($chunk));
            });

            $this->recordReply($session, $reply['content'], $reply['model'], $reply['tokens']);
        } catch (Throwable $exception) {
            Log::error('Semua penyedia LLM gagal.', ['session_id' => $session->id, 'exception' => $exception]);

            if ($answer === '') {
                $this->notice = 'Layanan AI sedang tidak tersedia. Silakan coba lagi nanti.';
            } else {
                // Only Ollama delivers a reply in pieces, so a partial reply is always its model's.
                $this->recordReply($session, $answer, config('services.llm.ollama.model'), null);
                $this->notice = 'Jawaban terputus di tengah jalan. Bagian yang sudah diterima tetap disimpan.';
            }
        }

        $this->loadMessages();
    }

    /**
     * The fenced code blocks of the latest reply, for the code panel beside the conversation.
     *
     * @return array<int, array{id: string, language: string, code: string}>
     */
    #[Computed]
    public function codeBlocks(): array
    {
        $lastReply = collect($this->visibleMessages)->last(fn (array $message) => $message['role'] === 'assistant');

        preg_match_all('/```([\w+#-]*)[^\n]*\n(.*?)```/s', $lastReply['content'] ?? '', $blocks, PREG_SET_ORDER);

        return array_map(fn (array $block, int $index) => [
            'id' => $lastReply['id'].'-'.$index,
            'language' => $block[1],
            'code' => rtrim($block[2]),
        ], $blocks, array_keys($blocks));
    }

    public function render(): View
    {
        return view('livewire.chat-box');
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return ['prompt' => ['required', 'string', 'min:3', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'prompt.required' => 'Pesan tidak boleh kosong.',
            'prompt.min' => 'Pesan minimal 3 karakter.',
            'prompt.max' => 'Pesan maksimal 1000 karakter.',
        ];
    }

    private function ownedSession(): ChatSession
    {
        return Auth::user()->chatSessions()->findOrFail($this->sessionId);
    }

    private function createSession(): ChatSession
    {
        $session = Auth::user()->chatSessions()->create(['title' => Str::limit(trim($this->prompt), 50)]);

        $this->sessionId = $session->id;
        $this->dispatch('session-created', sessionId: $session->id);

        return $session;
    }

    private function recordReply(ChatSession $session, string $content, string $model, ?int $tokens): void
    {
        $session->messages()->create([
            'user_id' => $session->user_id,
            'role' => 'assistant',
            'content' => $content,
            'model' => $model,
            'tokens_used' => $tokens,
        ]);
    }

    private function loadMessages(): void
    {
        $this->visibleMessages = $this->sessionId === null ? [] : $this->ownedSession()
            ->messages()
            ->latest('id')
            ->limit(self::VISIBLE_MESSAGES)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();
    }
}
