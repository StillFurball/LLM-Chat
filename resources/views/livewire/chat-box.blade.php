{{--
    `pending` shows the prompt as a bubble and empties the input the moment it is sent, instead of when the
    reply finishes. A prompt the server rejects is put back so the user can correct it.
--}}
<div class="flex min-h-0 flex-1">
<section
    class="flex min-h-0 min-w-0 flex-1 flex-col"
    x-data="{
        pending: '',
        init() {
            $wire.intercept('kirim', ({ onSend, onFinish }) => {
                onSend(() => { this.pending = $wire.prompt; $wire.prompt = '' })
                onFinish(() => { if ($wire.$errors.has('prompt')) $wire.prompt = this.pending; this.pending = '' })
            })
        },
    }"
>
    <flux:heading level="1" class="sr-only">{{ __('Chat dengan asisten karier') }}</flux:heading>

    <div
        {{-- `relative` keeps the absolutely positioned sr-only labels inside this scroll area instead of stretching the page. --}}
        class="relative min-h-0 flex-1 overflow-y-auto"
        role="log"
        aria-label="{{ __('Percakapan') }}"
        x-init="
            $el.scrollTop = $el.scrollHeight
            new MutationObserver(() => $el.scrollTop = $el.scrollHeight).observe($el, { childList: true, subtree: true, characterData: true })
        "
    >
        <div class="mx-auto flex min-h-full w-full max-w-3xl flex-col gap-6 px-4 py-6">
            @forelse ($visibleMessages as $message)
                @if ($message['role'] === 'user')
                    <div wire:key="message-{{ $message['id'] }}" class="max-w-[85%] self-end rounded-3xl bg-zinc-100 px-5 py-2.5 dark:bg-zinc-700">
                        <span class="sr-only">{{ __('Anda:') }}</span>
                        <p class="whitespace-pre-wrap">{{ $message['content'] }}</p>
                    </div>
                @else
                    <div wire:key="message-{{ $message['id'] }}">
                        <span class="sr-only">{{ __('Asisten:') }}</span>

                        {{-- A stored reply never changes, so wire:ignore lets highlight.js's markup survive re-renders. --}}
                        <div
                            wire:ignore
                            class="markdown"
                            x-init="$el.querySelectorAll('pre code').forEach((code) => hljs.highlightElement(code))"
                        >{!! Str::markdown($message['content'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
                    </div>
                @endif
            @empty
                <div class="m-auto flex max-w-xl flex-col items-center gap-6 py-8 text-center" x-show="! pending">
                    <div class="flex flex-col gap-2">
                        <h2 class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                            {{ __('Halo, :name.', ['name' => Str::before(auth()->user()->name, ' ')]) }}
                        </h2>
                        <p class="text-lg text-zinc-500 dark:text-zinc-400">{{ __('Mau menyiapkan apa untuk lamaran berikutnya?') }}</p>
                    </div>

                    <div class="flex flex-wrap justify-center gap-2">
                        @foreach ([
                            'Tinjau CV saya' => 'Saya ingin CV saya ditinjau. Apa saja yang perlu saya kirimkan lebih dulu?',
                            'Tulis surat lamaran' => 'Bantu saya menulis surat lamaran. Tanyakan dulu posisi dan pengalaman saya.',
                            'Latihan wawancara' => 'Ayo latihan wawancara kerja. Beri saya satu pertanyaan, lalu nilai jawaban saya.',
                            'Rencana belajar skill' => 'Buatkan rencana belajar 4 minggu untuk skill yang paling dicari di bidang saya. Tanyakan dulu bidangnya.',
                        ] as $label => $starter)
                            <button
                                type="button"
                                class="rounded-full border border-zinc-200 px-4 py-2 text-sm text-zinc-700 transition-colors hover:border-accent hover:text-accent-content focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent dark:border-zinc-600 dark:text-zinc-200"
                                x-on:click="$wire.prompt = @js($starter); $refs.input.focus()"
                            >
                                {{ __($label) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforelse

            <div x-show="pending" x-cloak class="max-w-[85%] self-end rounded-3xl bg-zinc-100 px-5 py-2.5 whitespace-pre-wrap dark:bg-zinc-700" x-text="pending"></div>

            <div wire:loading.block wire:target="kirim">
                <div wire:stream="answer" class="peer leading-7 whitespace-pre-wrap"></div>

                <div class="hidden items-center gap-2 text-zinc-500 peer-empty:flex dark:text-zinc-400">
                    <span class="flex gap-1" aria-hidden="true">
                        <span class="size-1.5 rounded-full bg-current motion-safe:animate-pulse"></span>
                        <span class="size-1.5 rounded-full bg-current motion-safe:animate-pulse motion-safe:[animation-delay:200ms]"></span>
                        <span class="size-1.5 rounded-full bg-current motion-safe:animate-pulse motion-safe:[animation-delay:400ms]"></span>
                    </span>
                    Memikirkan jawaban...
                </div>
            </div>

            @if ($notice)
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="$notice" role="alert" />
            @endif
        </div>
    </div>

    <div class="mx-auto w-full max-w-3xl shrink-0 px-4 pb-3">
        <form
            wire:submit="kirim"
            class="rounded-3xl border border-zinc-200 bg-white p-2 shadow-sm transition-shadow focus-within:border-accent focus-within:ring-3 focus-within:ring-accent/15 dark:border-zinc-600 dark:bg-zinc-900"
        >
            <label for="prompt" class="sr-only">{{ __('Pesan') }}</label>

            <textarea
                id="prompt"
                x-ref="input"
                wire:model="prompt"
                wire:loading.attr="disabled"
                wire:target="kirim"
                x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); $el.form.requestSubmit() }"
                rows="1"
                placeholder="{{ __('Tanyakan soal CV, lamaran, atau wawancara') }}"
                class="block field-sizing-content max-h-48 min-h-11 w-full resize-none bg-transparent px-3 py-2.5 outline-none placeholder:text-zinc-400 disabled:opacity-50"
            ></textarea>

            <div class="flex items-center justify-between gap-4 ps-3">
                <p class="flex gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                    <span x-text="`${$wire.prompt.length}/1000`" x-bind:class="$wire.prompt.length > 1000 && 'font-semibold text-red-600 dark:text-red-400'">0/1000</span>
                    <span class="max-sm:hidden">{{ __('Enter untuk kirim, Shift+Enter untuk baris baru') }}</span>
                </p>

                {{-- Flux's own spinner would replace the label; the loading text below is the indicator instead. --}}
                <flux:button type="submit" variant="primary" size="sm" icon:trailing="arrow-up" class="rounded-full!" :loading="false" wire:loading.attr="disabled" wire:target="kirim">
                    <span wire:loading.remove wire:target="kirim">{{ __('Kirim') }}</span>
                    {{-- Not passed through __(): the translator reads the trailing dots as key separators and drops one. --}}
                    <span wire:loading wire:target="kirim">Memikirkan jawaban...</span>
                </flux:button>
            </div>
        </form>

        <flux:error name="prompt" class="mt-2 px-3" />

        <p class="mt-2 text-center text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('Asisten bisa keliru. Periksa lagi informasi penting sebelum dipakai.') }}
        </p>
    </div>
</section>

@if ($this->codeBlocks)
    <aside class="hidden min-h-0 w-[26rem] shrink-0 flex-col gap-3 overflow-y-auto border-s border-zinc-200 p-4 xl:flex dark:border-zinc-700" aria-label="{{ __('Kode dari jawaban terakhir') }}">
        <flux:heading>{{ __('Kode dari jawaban terakhir') }}</flux:heading>

        @foreach ($this->codeBlocks as $block)
            {{-- wire:ignore keeps highlight.js's markup through re-renders; a new answer gets new keys, so new elements. --}}
            <figure wire:key="code-{{ $block['id'] }}" wire:ignore>
                @if ($block['language'])
                    <figcaption class="rounded-t-xl bg-zinc-700 px-4 py-1.5 font-mono text-xs text-zinc-200">{{ $block['language'] }}</figcaption>
                @endif

                <pre @class(['overflow-x-auto bg-zinc-900 text-sm', 'rounded-b-xl' => $block['language'], 'rounded-xl' => ! $block['language']])><code x-init="hljs.highlightElement($el)" @class(['language-'.$block['language'] => $block['language']])>{{ $block['code'] }}</code></pre>
            </figure>
        @endforeach
    </aside>
@endif
</div>
