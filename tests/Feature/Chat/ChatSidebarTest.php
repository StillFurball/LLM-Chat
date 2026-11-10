<?php

use App\Livewire\ChatSidebar;
use App\Models\ChatSession;
use App\Models\User;
use Livewire\Livewire;

test('sidebar lists only the sessions of the logged-in user, newest first', function () {
    $user = User::factory()->create();
    ChatSession::factory()->for($user)->create(['title' => 'Sesi lama saya']);
    ChatSession::factory()->for($user)->create(['title' => 'Sesi baru saya']);
    ChatSession::factory()->create(['title' => 'Sesi orang lain']);

    $sidebar = Livewire::actingAs($user)->test(ChatSidebar::class);

    $sidebar->assertSeeInOrder(['Sesi baru saya', 'Sesi lama saya'])
        ->assertDontSee('Sesi orang lain');
});

test('selecting a session dispatches session-selected with its id', function () {
    $session = ChatSession::factory()->create();

    $sidebar = Livewire::actingAs(User::sole())->test(ChatSidebar::class)
        ->call('selectSession', $session->id);

    $sidebar->assertDispatched('session-selected', sessionId: $session->id)
        ->assertSet('activeSessionId', $session->id);
});

test('starting a new chat dispatches session-selected without an id', function () {
    $session = ChatSession::factory()->create();

    $sidebar = Livewire::actingAs(User::sole())->test(ChatSidebar::class)
        ->call('selectSession', $session->id)
        ->call('selectSession');

    $sidebar->assertDispatched('session-selected', sessionId: null)
        ->assertSet('activeSessionId', null);
});

test('sidebar shows a session created after it was rendered', function () {
    $user = User::factory()->create();
    $sidebar = Livewire::actingAs($user)->test(ChatSidebar::class);
    $session = ChatSession::factory()->for($user)->create(['title' => 'Sesi yang baru dibuat']);

    $sidebar->dispatch('session-created', sessionId: $session->id);

    $sidebar->assertSee('Sesi yang baru dibuat')
        ->assertSet('activeSessionId', $session->id);
});
