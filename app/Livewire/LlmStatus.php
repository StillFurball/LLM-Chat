<?php

namespace App\Livewire;

use App\Services\LlmClient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Re-rendered by wire:poll, so the chat page shows whether the local model is ready without a refresh.
 */
class LlmStatus extends Component
{
    private LlmClient $llm;

    public function boot(LlmClient $llm): void
    {
        $this->llm = $llm;
    }

    public function render(): View
    {
        return view('livewire.llm-status', [
            'status' => $this->llm->status(),
            'model' => config('services.llm.ollama.model'),
        ]);
    }
}
