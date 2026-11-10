<div wire:poll.15s role="status" class="flex items-center gap-2 rounded-full bg-zinc-100 px-3 py-1.5 text-xs text-zinc-600 dark:bg-zinc-700/60 dark:text-zinc-300">
    <span
        aria-hidden="true"
        @class([
            'size-2 shrink-0 rounded-full',
            'bg-green-500' => $status === 'ready',
            'bg-amber-500' => $status === 'idle',
            'bg-red-500' => $status === 'offline',
        ])
    ></span>

    @if ($status === 'ready')
        {{ __('Model :model siap menjawab.', ['model' => $model]) }}
    @elseif ($status === 'idle')
        {{ __('Model :model belum dimuat. Jawaban pertama butuh waktu lebih lama.', ['model' => $model]) }}
    @else
        {{ __('Ollama tidak terjangkau. Jawaban bergantung pada layanan cadangan.') }}
    @endif
</div>
