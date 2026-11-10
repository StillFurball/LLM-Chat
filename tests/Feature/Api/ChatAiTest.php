<?php

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.llm.system_prompt' => 'Anda adalah asisten karier.',
        'services.llm.ollama.url' => 'http://ollama.test',
        'services.llm.ollama.model' => 'qwen3:14b',
        'services.llm.senopati.url' => 'http://senopati.test/api/chat',
        'services.llm.senopati.token' => 'senopati-token',
        'services.llm.senopati.model' => 'senopati-model',
    ]);
});

test('chat returns 401 without a token', function (string $route) {
    $response = $this->postJson(route($route), ['prompt' => 'Halo']);

    $response->assertUnauthorized();
    $this->assertDatabaseCount('chat_messages', 0);
})->with(['api.chat-ai.store', 'api.chat-ai.stream']);

test('chat returns 422 for an invalid prompt', function (string $prompt, string $message) {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson(route('api.chat-ai.store'), ['prompt' => $prompt]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['prompt' => $message]);
    $this->assertDatabaseCount('chat_messages', 0);
})->with([
    'empty' => ['', 'The prompt field is required.'],
    'longer than 1000 characters' => [str_repeat('a', 1001), 'The prompt field must not be greater than 1000 characters.'],
]);

test('chat returns 422 for a session that belongs to another user', function () {
    $otherUsersSession = ChatSession::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson(route('api.chat-ai.store'), [
        'prompt' => 'Halo',
        'session_id' => $otherUsersSession->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['session_id' => 'The selected session id is invalid.']);
    $this->assertDatabaseCount('chat_messages', 0);
});

test('chat stores both messages and returns the reply', function () {
    $this->travelTo('2026-08-28 09:30:00');
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    Http::fake(['ollama.test/api/chat' => Http::response([
        'model' => 'qwen3:14b',
        'message' => ['role' => 'assistant', 'content' => 'Middleware adalah lapisan penyaring request.'],
        'done' => true,
        'prompt_eval_count' => 40,
        'eval_count' => 12,
    ])]);

    $response = $this->postJson(route('api.chat-ai.store'), ['prompt' => 'Jelaskan middleware!']);

    $session = ChatSession::sole();
    $response->assertCreated()->assertExactJson([
        'status' => 'success',
        'data' => [
            'session_id' => $session->id,
            'model_used' => 'qwen3:14b',
            'prompt' => 'Jelaskan middleware!',
            'ai_response' => 'Middleware adalah lapisan penyaring request.',
            'timestamp' => '2026-08-28 09:30:00',
        ],
    ]);
    expect($session)->user_id->toBe($user->id)->title->toBe('Jelaskan middleware!');
    $this->assertDatabaseCount('chat_messages', 2);
    $this->assertDatabaseHas('chat_messages', [
        'chat_session_id' => $session->id,
        'user_id' => $user->id,
        'role' => 'user',
        'content' => 'Jelaskan middleware!',
        'model' => null,
        'tokens_used' => null,
    ]);
    $this->assertDatabaseHas('chat_messages', [
        'chat_session_id' => $session->id,
        'user_id' => $user->id,
        'role' => 'assistant',
        'content' => 'Middleware adalah lapisan penyaring request.',
        'model' => 'qwen3:14b',
        'tokens_used' => 52,
    ]);
    Http::assertSent(fn (Request $request) => $request->url() === 'http://ollama.test/api/chat'
        && $request['model'] === 'qwen3:14b'
        && $request['stream'] === false
        && $request['messages'] === [
            ['role' => 'system', 'content' => 'Anda adalah asisten karier.'],
            ['role' => 'user', 'content' => 'Jelaskan middleware!'],
        ]);
});

test('chat continues an existing session and sends its history to the model', function () {
    $session = ChatSession::factory()
        ->has(ChatMessage::factory()->state(['content' => 'Halo']), 'messages')
        ->has(ChatMessage::factory()->assistant()->state(['content' => 'Hai, ada yang bisa dibantu?']), 'messages')
        ->create();
    Sanctum::actingAs(User::sole());
    Http::fake(['ollama.test/api/chat' => Http::response([
        'model' => 'qwen3:14b',
        'message' => ['role' => 'assistant', 'content' => 'Tentu, kirimkan CV Anda.'],
    ])]);

    $response = $this->postJson(route('api.chat-ai.store'), [
        'prompt' => 'Bantu perbaiki CV saya',
        'session_id' => $session->id,
    ]);

    $response->assertCreated()->assertJsonPath('data.session_id', $session->id);
    $this->assertDatabaseCount('chat_sessions', 1);
    $this->assertDatabaseCount('chat_messages', 4);
    Http::assertSent(fn (Request $request) => $request['messages'] === [
        ['role' => 'system', 'content' => 'Anda adalah asisten karier.'],
        ['role' => 'user', 'content' => 'Halo'],
        ['role' => 'assistant', 'content' => 'Hai, ada yang bisa dibantu?'],
        ['role' => 'user', 'content' => 'Bantu perbaiki CV saya'],
    ]);
});

test('chat falls back to Senopati when Ollama is unreachable', function () {
    Sanctum::actingAs(User::factory()->create());
    Http::fake([
        'ollama.test/api/chat' => Http::failedConnection(),
        'senopati.test/api/chat' => Http::response(['result' => 'Jawaban dari Senopati.']),
    ]);

    $response = $this->postJson(route('api.chat-ai.store'), ['prompt' => 'Halo']);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.model_used', 'senopati-model')
        ->assertJsonPath('data.ai_response', 'Jawaban dari Senopati.');
    $this->assertDatabaseHas('chat_messages', [
        'role' => 'assistant',
        'content' => 'Jawaban dari Senopati.',
        'model' => 'senopati-model',
        'tokens_used' => null,
    ]);
    Http::assertSent(fn (Request $request) => $request->url() === 'http://senopati.test/api/chat'
        && $request->hasHeader('Authorization', 'Bearer senopati-token')
        && str_contains($request['prompt'], 'Halo'));
});

test('chat returns 503 and keeps the prompt when every provider fails', function () {
    Sanctum::actingAs(User::factory()->create());
    Http::fake([
        'ollama.test/api/chat' => Http::response(['error' => 'model not found'], 500),
        'senopati.test/api/chat' => Http::failedConnection(),
    ]);
    Log::spy();

    $response = $this->postJson(route('api.chat-ai.store'), ['prompt' => 'Halo']);

    $response->assertServiceUnavailable()->assertExactJson([
        'status' => 'error',
        'message' => 'Layanan AI sedang tidak tersedia. Silakan coba lagi nanti.',
    ]);
    $this->assertDatabaseCount('chat_messages', 1);
    $this->assertDatabaseHas('chat_messages', ['role' => 'user', 'content' => 'Halo']);
    Log::shouldHaveReceived('error')->once();
});

test('chat stream sends the reply as server-sent events and stores it', function () {
    Sanctum::actingAs(User::factory()->create());
    Http::fake(['ollama.test/api/chat' => Http::response(implode("\n", [
        json_encode(['model' => 'qwen3:14b', 'message' => ['role' => 'assistant', 'content' => 'Halo'], 'done' => false]),
        json_encode(['model' => 'qwen3:14b', 'message' => ['role' => 'assistant', 'content' => " dunia\n"], 'done' => false]),
        json_encode(['model' => 'qwen3:14b', 'message' => ['role' => 'assistant', 'content' => ''], 'done' => true, 'prompt_eval_count' => 10, 'eval_count' => 5]),
    ])."\n")]);

    $response = $this->postJson(route('api.chat-ai.stream'), ['prompt' => 'Sapa saya']);
    $events = $response->streamedContent();

    $session = ChatSession::sole();
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/event-stream');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    expect($events)->toBe(
        'data: {"content":"Halo"}'."\n\n"
        .'data: {"content":" dunia\n"}'."\n\n"
        .'data: {"done":true,"session_id":'.$session->id.',"model_used":"qwen3:14b"}'."\n\n"
    );
    $this->assertDatabaseHas('chat_messages', [
        'chat_session_id' => $session->id,
        'role' => 'assistant',
        'content' => "Halo dunia\n",
        'model' => 'qwen3:14b',
        'tokens_used' => 15,
    ]);
    Http::assertSent(fn (Request $request) => $request['stream'] === true);
});

test('chat stream sends an error event and keeps the prompt when every provider fails', function () {
    Sanctum::actingAs(User::factory()->create());
    Http::fake([
        'ollama.test/api/chat' => Http::failedConnection(),
        'senopati.test/api/chat' => Http::response(status: 500),
    ]);

    $events = $this->postJson(route('api.chat-ai.stream'), ['prompt' => 'Halo'])->streamedContent();

    expect($events)->toBe(
        'event: error'."\n"
        .'data: {"status":"error","message":"Layanan AI sedang tidak tersedia. Silakan coba lagi nanti."}'."\n\n"
    );
    $this->assertDatabaseCount('chat_messages', 1);
    $this->assertDatabaseHas('chat_messages', ['role' => 'user', 'content' => 'Halo']);
});
