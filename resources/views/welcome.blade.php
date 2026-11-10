<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => __('Asisten AI untuk pencari kerja')])
    </head>
    <body class="flex min-h-screen flex-col bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-6 py-5">
            <x-app-logo href="{{ route('home') }}" />

            <nav class="flex items-center gap-2" aria-label="{{ __('Akun') }}">
                @auth
                    <flux:button :href="route('chat')" variant="primary" size="sm">{{ __('Buka chat') }}</flux:button>
                @else
                    <flux:button :href="route('login')" variant="ghost" size="sm">{{ __('Masuk') }}</flux:button>

                    @if (Route::has('register'))
                        <flux:button :href="route('register')" variant="primary" size="sm">{{ __('Daftar') }}</flux:button>
                    @endif
                @endauth
            </nav>
        </header>

        <main class="mx-auto grid w-full max-w-5xl flex-1 items-center gap-12 px-6 py-10 lg:grid-cols-2">
            <div class="flex flex-col items-start gap-6">
                <h1 class="text-4xl leading-tight font-bold tracking-tight text-balance sm:text-5xl">
                    {{ __('Siapkan lamaran berikutnya bersama asisten karier.') }}
                </h1>

                <p class="max-w-md text-lg text-zinc-600 dark:text-zinc-300">
                    {{ __('Minta tinjauan CV, draf surat lamaran, atau latihan wawancara. Jawaban muncul langsung saat ditulis, dan setiap percakapan tersimpan.') }}
                </p>

                <flux:button :href="route('chat')" variant="primary">{{ __('Mulai chat') }}</flux:button>
            </div>

            {{-- A sample exchange, so the page shows the product instead of describing it. --}}
            <div class="flex flex-col gap-4 rounded-3xl border border-zinc-200 p-6 dark:border-zinc-700" aria-label="{{ __('Contoh percakapan') }}" role="img">
                <p class="max-w-[85%] self-end rounded-3xl bg-zinc-100 px-5 py-2.5 dark:bg-zinc-700">
                    {{ __('Saya fresh graduate Informatika, mau melamar backend developer. CV saya harus menonjolkan apa?') }}
                </p>

                <div class="markdown">
                    <p>{{ __('Untuk posisi backend tingkat pemula, perekrut biasanya mencari tiga hal:') }}</p>
                    <ol>
                        <li>{{ __('Proyek yang benar-benar Anda bangun, lengkap dengan teknologinya.') }}</li>
                        <li>{{ __('Bukti Anda paham database dan API, bukan sekadar daftar bahasa.') }}</li>
                        <li>{{ __('Tautan repositori yang bisa mereka buka.') }}</li>
                    </ol>
                    <p>{{ __('Boleh kirim isi CV Anda sekarang? Saya tinjau bagian per bagian.') }}</p>
                </div>
            </div>
        </main>

        @fluxScripts
    </body>
</html>
