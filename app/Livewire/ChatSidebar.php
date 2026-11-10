<?php

namespace App\Livewire;

use App\Models\ChatSession;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class ChatSidebar extends Component
{
    /**
     * Only used to highlight the open session; ChatBox is what checks ownership.
     */
    #[Url(as: 'sesi')]
    public ?int $activeSessionId = null;

    /**
     * Ask ChatBox to open a session, or to start a new chat when no id is given.
     */
    public function selectSession(?int $sessionId = null): void
    {
        $this->activeSessionId = $sessionId;

        $this->dispatch('session-selected', sessionId: $sessionId);
    }

    /**
     * Highlight the session ChatBox just created; re-rendering picks it up in the list.
     */
    #[On('session-created')]
    public function showCreatedSession(int $sessionId): void
    {
        $this->activeSessionId = $sessionId;
    }

    /**
     * @return Collection<int, ChatSession>
     */
    #[Computed]
    public function sessions(): Collection
    {
        // shortcut: shows the 50 newest sessions, add pagination or search once users have more.
        return Auth::user()->chatSessions()->latest('id')->limit(50)->get();
    }

    public function render(): View
    {
        return view('livewire.chat-sidebar');
    }
}
