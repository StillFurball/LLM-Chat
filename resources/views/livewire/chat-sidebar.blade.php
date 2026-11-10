{{-- Picking a chat on a phone also closes the slide-over sidebar it lives in. --}}
<aside
    class="flex min-h-0 flex-1 flex-col gap-4"
    aria-label="{{ __('Riwayat chat') }}"
    x-data="{ closeOnMobile() { window.innerWidth < 1024 && $dispatch('flux-sidebar-toggle') } }"
>
    <flux:button wire:click="selectSession" x-on:click="closeOnMobile()" variant="primary" icon="pencil-square" class="w-full">
        {{ __('Chat baru') }}
    </flux:button>

    <div class="flex min-h-0 flex-1 flex-col gap-1">
        <flux:heading size="sm" class="px-3 text-zinc-500 dark:text-zinc-400">{{ __('Riwayat') }}</flux:heading>

        <flux:navlist class="min-h-0 flex-1 overflow-y-auto">
            @forelse ($this->sessions as $session)
                <flux:navlist.item
                    wire:key="session-{{ $session->id }}"
                    wire:click="selectSession({{ $session->id }})"
                    x-on:click="closeOnMobile()"
                    :current="$session->id === $activeSessionId"
                    :title="$session->title"
                    class="[&>div]:min-w-0 [&>div]:truncate [&>div]:leading-normal"
                >
                    {{ $session->title }}
                </flux:navlist.item>
            @empty
                <flux:text size="sm" class="px-3 py-2">{{ __('Percakapan Anda akan muncul di sini.') }}</flux:text>
            @endforelse
        </flux:navlist>
    </div>
</aside>
