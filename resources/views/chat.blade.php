<x-layouts::app.sidebar :title="__('Chat')">
    {{-- Full-bleed and exactly one viewport tall (minus the mobile header), so only the conversation scrolls. --}}
    <flux:main class="flex h-[calc(100dvh-3.5rem)] flex-col p-0! lg:h-dvh">
        <div class="flex shrink-0 items-center px-4 pt-3 lg:px-6">
            <livewire:llm-status />
        </div>

        <livewire:chat-box />
    </flux:main>
</x-layouts::app.sidebar>
