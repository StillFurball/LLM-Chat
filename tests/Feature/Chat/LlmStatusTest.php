<?php

use App\Livewire\LlmStatus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.llm.ollama.url' => 'http://ollama.test',
        'services.llm.ollama.model' => 'qwen3:14b',
    ]);
});

test('status tells whether the configured model is loaded in ollama', function (array $loadedModels, string $text) {
    Http::fake(['ollama.test/api/ps' => Http::response(['models' => $loadedModels])]);

    $status = Livewire::test(LlmStatus::class);

    $status->assertSee($text);
})->with([
    'configured model loaded' => [[['name' => 'qwen3:14b']], 'Model qwen3:14b siap menjawab.'],
    'only another model loaded' => [[['name' => 'llama3:latest']], 'Model qwen3:14b belum dimuat.'],
    'nothing loaded' => [[], 'Model qwen3:14b belum dimuat.'],
]);

test('status reports ollama as unreachable when the connection fails', function () {
    Http::fake(['ollama.test/api/ps' => Http::failedConnection()]);

    $status = Livewire::test(LlmStatus::class);

    $status->assertSee('Ollama tidak terjangkau.');
});
