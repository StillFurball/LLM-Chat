<?php

use App\Livewire\ChatBox;
use App\Livewire\ChatSidebar;
use App\Livewire\LlmStatus;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('chat'));

    $response->assertRedirect(route('login'));
});

test('authenticated users see the chat sidebar, model status and chat box', function () {
    $this->actingAs(User::factory()->create());
    Http::preventStrayRequests();
    config(['services.llm.ollama.url' => 'http://ollama.test']);
    Http::fake(['ollama.test/api/ps' => Http::response(['models' => []])]);

    $response = $this->get(route('chat'));

    $response->assertOk()
        ->assertSeeLivewire(ChatSidebar::class)
        ->assertSeeLivewire(LlmStatus::class)
        ->assertSeeLivewire(ChatBox::class);
});
