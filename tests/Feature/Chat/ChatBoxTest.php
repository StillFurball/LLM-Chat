<?php

use App\Livewire\ChatBox;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.llm.system_prompt' => 'Anda adalah asisten karier.',
        'services.llm.ollama.url' => 'http://ollama.test',
        'services.llm.ollama.model' => 'qwen3:14b',
        'services.llm.senopati.url' => 'http://senopati.test/api/chat',
    ]);
});

/**
 * The body Ollama sends for a streamed reply: one JSON object per line.
 *
 * @param  array<int, array<string, mixed>>  $events
 */
function ollamaStream(array $events): string
{
    return collect($events)->map(fn (array $event) => json_encode($event))->implode("\n")."\n";
}

test('kirim rejects an invalid prompt without storing anything', function (string $prompt, string $message) {
    $chatBox = Livewire::actingAs(User::factory()->create())->test(ChatBox::class)
        ->set('prompt', $prompt)
        ->call('kirim');

    $chatBox->assertHasErrors('prompt')->assertSee($message);
    $this->assertDatabaseCount('chat_sessions', 0);
    $this->assertDatabaseCount('chat_messages', 0);
})->with([
    'empty' => ['', 'Pesan tidak boleh kosong.'],
    'shorter than 3 characters' => ['ab', 'Pesan minimal 3 karakter.'],
    'longer than 1000 characters' => [str_repeat('a', 1001), 'Pesan maksimal 1000 karakter.'],
]);

test('kirim starts a session, stores the prompt and the reply, and announces the new session', function () {
    $user = User::factory()->create();
    Http::fake(['ollama.test/api/chat' => Http::response(ollamaStream([
        ['model' => 'qwen3:14b', 'message' => ['content' => 'Tentu, '], 'done' => false],
        ['model' => 'qwen3:14b', 'message' => ['content' => 'kirimkan CV Anda.'], 'done' => false],
        ['model' => 'qwen3:14b', 'message' => ['content' => ''], 'done' => true, 'prompt_eval_count' => 30, 'eval_count' => 8],
    ]))]);

    $chatBox = Livewire::actingAs($user)->test(ChatBox::class)
        ->set('prompt', 'Bantu perbaiki CV saya')
        ->call('kirim');

    $session = $user->chatSessions()->sole();
    $chatBox->assertHasNoErrors()
        ->assertSet('prompt', '')
        ->assertSet('sessionId', $session->id)
        ->assertDispatched('session-created', sessionId: $session->id)
        ->assertSeeInOrder(['Bantu perbaiki CV saya', 'Tentu, kirimkan CV Anda.']);
    expect($session->title)->toBe('Bantu perbaiki CV saya');
    $this->assertDatabaseCount('chat_messages', 2);
    $this->assertDatabaseHas('chat_messages', [
        'chat_session_id' => $session->id,
        'user_id' => $user->id,
        'role' => 'user',
        'content' => 'Bantu perbaiki CV saya',
    ]);
    $this->assertDatabaseHas('chat_messages', [
        'chat_session_id' => $session->id,
        'user_id' => $user->id,
        'role' => 'assistant',
        'content' => 'Tentu, kirimkan CV Anda.',
        'model' => 'qwen3:14b',
        'tokens_used' => 38,
    ]);
});

test('kirim continues the open session and sends its history to the model', function () {
    $session = ChatSession::factory()
        ->has(ChatMessage::factory()->state(['content' => 'Halo']), 'messages')
        ->has(ChatMessage::factory()->assistant()->state(['content' => 'Hai, ada yang bisa dibantu?']), 'messages')
        ->create();
    Http::fake(['ollama.test/api/chat' => Http::response(ollamaStream([
        ['model' => 'qwen3:14b', 'message' => ['content' => 'Tentu.'], 'done' => true],
    ]))]);

    $chatBox = Livewire::actingAs(User::sole())->test(ChatBox::class)
        ->dispatch('session-selected', sessionId: $session->id)
        ->set('prompt', 'Bantu perbaiki CV saya')
        ->call('kirim');

    $chatBox->assertNotDispatched('session-created')
        ->assertSeeInOrder(['Halo', 'Hai, ada yang bisa dibantu?', 'Bantu perbaiki CV saya', 'Tentu.']);
    $this->assertDatabaseCount('chat_sessions', 1);
    $this->assertDatabaseCount('chat_messages', 4);
    Http::assertSent(fn (Request $request) => $request['messages'] === [
        ['role' => 'system', 'content' => 'Anda adalah asisten karier.'],
        ['role' => 'user', 'content' => 'Halo'],
        ['role' => 'assistant', 'content' => 'Hai, ada yang bisa dibantu?'],
        ['role' => 'user', 'content' => 'Bantu perbaiki CV saya'],
    ]);
});

test('chat box shows only the 20 latest messages of the session in the url', function () {
    $session = ChatSession::factory()
        ->has(ChatMessage::factory()->count(21)->sequence(
            fn ($sequence) => ['content' => 'Pesan nomor '.($sequence->index + 1).'.'],
        ), 'messages')
        ->create();

    $chatBox = Livewire::actingAs(User::sole())->withQueryParams(['sesi' => $session->id])->test(ChatBox::class);

    $chatBox->assertSeeInOrder(['Pesan nomor 2.', 'Pesan nomor 21.'])
        ->assertDontSee('Pesan nomor 1.');
});

test('code panel shows the code blocks of the latest reply only', function () {
    $session = ChatSession::factory()
        ->has(ChatMessage::factory()->assistant()->state(['content' => "Versi lama:\n```js\nconsole.log('lama')\n```"]), 'messages')
        ->has(ChatMessage::factory()->assistant()->state(['content' => "Versi baru:\n```php\necho 'baru';\n```\nSelesai."]), 'messages')
        ->create();

    $chatBox = Livewire::actingAs(User::sole())->withQueryParams(['sesi' => $session->id])->test(ChatBox::class);

    [$olderReply, $latestReply] = $session->messages;
    $chatBox->assertSee('Kode dari jawaban terakhir')
        ->assertSeeHtml('wire:key="code-'.$latestReply->id.'-0"')
        ->assertDontSeeHtml('wire:key="code-'.$olderReply->id.'-0"');
});

test('chat box renders the markdown of a reply and escapes html inside it', function () {
    $session = ChatSession::factory()
        ->has(ChatMessage::factory()->assistant()->state(['content' => 'Ini **penting** <script>alert(1)</script>']), 'messages')
        ->create();

    $chatBox = Livewire::actingAs(User::sole())->withQueryParams(['sesi' => $session->id])->test(ChatBox::class);

    $chatBox->assertSeeHtml('<strong>penting</strong>')
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->assertDontSeeHtml('<script>alert(1)</script>');
});

test('code panel is hidden when the latest reply has no code block', function () {
    $session = ChatSession::factory()
        ->has(ChatMessage::factory()->assistant()->state(['content' => 'Tidak ada kode di sini.']), 'messages')
        ->create();

    $chatBox = Livewire::actingAs(User::sole())->withQueryParams(['sesi' => $session->id])->test(ChatBox::class);

    $chatBox->assertDontSee('Kode dari jawaban terakhir');
});

test('chat box returns 404 for a session of another user in the url', function () {
    $otherUsersSession = ChatSession::factory()
        ->has(ChatMessage::factory()->state(['content' => 'Rahasia orang lain']), 'messages')
        ->create();

    $chatBox = Livewire::actingAs(User::factory()->create())
        ->withQueryParams(['sesi' => $otherUsersSession->id])
        ->test(ChatBox::class);

    $chatBox->assertNotFound()->assertDontSee('Rahasia orang lain');
});

test('selecting a session of another user returns 404 and never renders its messages', function () {
    $otherUsersSession = ChatSession::factory()
        ->has(ChatMessage::factory()->state(['content' => 'Rahasia orang lain']), 'messages')
        ->create();

    $chatBox = Livewire::actingAs(User::factory()->create())->test(ChatBox::class)
        ->dispatch('session-selected', sessionId: $otherUsersSession->id);

    $chatBox->assertNotFound()->assertDontSee('Rahasia orang lain');
});

test('kirim returns 404 and stores nothing for a session of another user', function () {
    $otherUsersSession = ChatSession::factory()->create();

    $chatBox = Livewire::actingAs(User::factory()->create())->test(ChatBox::class)
        ->set('sessionId', $otherUsersSession->id)
        ->set('prompt', 'Halo semuanya')
        ->call('kirim');

    $chatBox->assertNotFound();
    $this->assertDatabaseCount('chat_messages', 0);
});

test('kirim keeps the prompt and shows a notice when every provider fails', function () {
    $user = User::factory()->create();
    Http::fake([
        'ollama.test/api/chat' => Http::failedConnection(),
        'senopati.test/api/chat' => Http::response(status: 500),
    ]);

    $chatBox = Livewire::actingAs($user)->test(ChatBox::class)
        ->set('prompt', 'Bantu perbaiki CV saya')
        ->call('kirim');

    $chatBox->assertOk()
        ->assertSeeInOrder(['Bantu perbaiki CV saya', 'Layanan AI sedang tidak tersedia. Silakan coba lagi nanti.']);
    $this->assertDatabaseCount('chat_messages', 1);
    $this->assertDatabaseHas('chat_messages', ['user_id' => $user->id, 'role' => 'user', 'content' => 'Bantu perbaiki CV saya']);
});

test('kirim stores the part of the reply that arrived when the stream breaks midway', function () {
    Http::fake(['ollama.test/api/chat' => Http::response(ollamaStream([
        ['model' => 'qwen3:14b', 'message' => ['content' => 'Langkah pertama adalah'], 'done' => false],
        ['error' => 'model runner has unexpectedly stopped'],
    ]))]);

    $chatBox = Livewire::actingAs(User::factory()->create())->test(ChatBox::class)
        ->set('prompt', 'Bantu perbaiki CV saya')
        ->call('kirim');

    $chatBox->assertOk()
        ->assertSeeInOrder(['Langkah pertama adalah', 'Jawaban terputus di tengah jalan.']);
    $this->assertDatabaseHas('chat_messages', [
        'role' => 'assistant',
        'content' => 'Langkah pertama adalah',
        'model' => 'qwen3:14b',
        'tokens_used' => null,
    ]);
});
