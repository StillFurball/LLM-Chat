<?php

namespace App\Services;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class LlmClient
{
    /**
     * Ask the LLM for a complete reply: Ollama first, Senopati when Ollama fails.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, model: string, tokens: int|null}
     *
     * @throws Throwable when every provider fails
     */
    public function chat(array $messages): array
    {
        $messages = $this->withSystemPrompt($messages);

        try {
            return $this->askOllama($messages);
        } catch (Throwable $exception) {
            return $this->fallBackToSenopati($exception, $messages);
        }
    }

    /**
     * Ask the LLM for a reply and hand each piece of it to the callback as it arrives.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  callable(string): void  $onChunk
     * @return array{content: string, model: string, tokens: int|null}
     *
     * @throws Throwable when every provider fails
     */
    public function stream(array $messages, callable $onChunk): array
    {
        $messages = $this->withSystemPrompt($messages);
        $hasStreamed = false;

        try {
            return $this->streamOllama($messages, function (string $chunk) use ($onChunk, &$hasStreamed): void {
                $hasStreamed = true;
                $onChunk($chunk);
            });
        } catch (Throwable $exception) {
            // A partial reply already reached the caller; a fallback answer would be glued onto it.
            if ($hasStreamed) {
                throw $exception;
            }

            $reply = $this->fallBackToSenopati($exception, $messages);
            $onChunk($reply['content']);

            return $reply;
        }
    }

    /**
     * Whether Ollama can answer right now: its model is in memory, has to be loaded first, or Ollama is unreachable.
     *
     * @return 'ready'|'idle'|'offline'
     */
    public function status(): string
    {
        try {
            $loadedModels = Http::acceptJson()->connectTimeout(1)->timeout(2)
                ->get(config('services.llm.ollama.url').'/api/ps')
                ->throw()
                ->collect('models')
                ->pluck('name');
        } catch (Throwable) {
            return 'offline';
        }

        $model = config('services.llm.ollama.model');

        // Ollama lists a model pulled without a tag as "name:latest".
        return $loadedModels->contains($model) || $loadedModels->contains($model.':latest') ? 'ready' : 'idle';
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    private function withSystemPrompt(array $messages): array
    {
        return [['role' => 'system', 'content' => config('services.llm.system_prompt')], ...$messages];
    }

    private function http(): PendingRequest
    {
        // PHP's default 30s max_execution_time is shorter than a local LLM reply; the HTTP timeouts bound the request instead.
        set_time_limit(0);

        return Http::acceptJson()->connectTimeout(5)->timeout(config('services.llm.timeout'));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, model: string, tokens: int|null}
     */
    private function askOllama(array $messages): array
    {
        $response = $this->http()
            ->post(config('services.llm.ollama.url').'/api/chat', [
                'model' => config('services.llm.ollama.model'),
                'messages' => $messages,
                'stream' => false,
            ])
            ->throw();

        return $this->ollamaReply($response->json() ?? [], $response->json('message.content'));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  callable(string): void  $onChunk
     * @return array{content: string, model: string, tokens: int|null}
     */
    private function streamOllama(array $messages, callable $onChunk): array
    {
        $body = $this->http()
            ->withOptions(['stream' => true])
            ->post(config('services.llm.ollama.url').'/api/chat', [
                'model' => config('services.llm.ollama.model'),
                'messages' => $messages,
                'stream' => true,
            ])
            ->throw()
            ->toPsrResponse()
            ->getBody();

        $content = '';
        $lastEvent = [];

        while (! $body->eof()) {
            $event = json_decode(Utils::readLine($body), true);

            if (! is_array($event)) {
                continue;
            }

            if (isset($event['error'])) {
                throw new RuntimeException('Ollama: '.$event['error']);
            }

            $chunk = $event['message']['content'] ?? '';

            if ($chunk !== '') {
                $content .= $chunk;
                $onChunk($chunk);
            }

            $lastEvent = $event;
        }

        return $this->ollamaReply($lastEvent, $content);
    }

    /**
     * @param  array{model?: string, prompt_eval_count?: int, eval_count?: int}  $data  the Ollama response, or its final stream event
     * @return array{content: string, model: string, tokens: int|null}
     */
    private function ollamaReply(array $data, ?string $content): array
    {
        if (blank($content)) {
            throw new UnexpectedValueException('Ollama mengembalikan jawaban kosong.');
        }

        return [
            'content' => $content,
            'model' => $data['model'] ?? config('services.llm.ollama.model'),
            'tokens' => isset($data['prompt_eval_count'], $data['eval_count'])
                ? $data['prompt_eval_count'] + $data['eval_count']
                : null,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, model: string, tokens: int|null}
     */
    private function fallBackToSenopati(Throwable $exception, array $messages): array
    {
        Log::warning('Ollama gagal, beralih ke Senopati.', ['error' => $exception->getMessage()]);

        if (blank(config('services.llm.senopati.url'))) {
            throw $exception;
        }

        return $this->askSenopati($messages);
    }

    /**
     * shortcut: format API Senopati belum diverifikasi. Ini mengikuti contoh slide (kirim `prompt`, baca `result`),
     * memakai SENOPATI_URL sebagai endpoint penuh, dan belum mengirim SENOPATI_API_KEY. Koreksi method ini saja
     * setelah dokumentasi asli Senopati tersedia.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, model: string, tokens: int|null}
     */
    private function askSenopati(array $messages): array
    {
        $token = config('services.llm.senopati.token');

        $response = $this->http()
            ->when(filled($token), fn (PendingRequest $http) => $http->withToken($token))
            ->post(config('services.llm.senopati.url'), [
                'model' => config('services.llm.senopati.model'),
                'prompt' => collect($messages)
                    ->map(fn (array $message) => $message['role'].': '.$message['content'])
                    ->implode("\n\n"),
            ])
            ->throw();

        $content = $response->json('result');

        if (blank($content)) {
            throw new UnexpectedValueException('Senopati mengembalikan jawaban kosong.');
        }

        return [
            'content' => $content,
            'model' => config('services.llm.senopati.model'),
            'tokens' => null,
        ];
    }
}
