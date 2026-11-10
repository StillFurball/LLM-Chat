# Instruksi AI — Tahap 2: Secure & Reactive AI Chat Hub (Pertemuan 12)

**Prasyarat:** seluruh "Definisi Selesai" di [01-pertemuan-11-api-hub.md](01-pertemuan-11-api-hub.md) sudah
terpenuhi. Periksa dulu: model `ChatSession` dan `ChatMessage` ada, `App\Services\LlmClient` ada, dan
`php artisan test --compact` lulus. Jika belum, berhenti dan selesaikan Tahap 1.

## Tujuan

Antarmuka chat reaktif berbasis Livewire di atas backend Tahap 1: pengguna yang login bisa mengirim prompt,
melihat balon chat, melihat loading state, dan riwayatnya tersimpan di database.

## Aturan kerja

- Aktifkan skill `livewire-development`, `fluxui-development`, dan `tailwindcss-development` sebelum menulis kode.
  Proyek memakai **Livewire 4** — sintaks di slide kuliah sebagian bergaya Livewire 3; ikuti API versi terpasang
  (cek dengan `search-docs` Laravel Boost), bukan slide.
- Tiru format komponen yang sudah ada (`app/Livewire/Settings/*` + `resources/views/livewire/settings/*`)
  dan layout `resources/views/layouts/app.blade.php`. Pakai komponen Flux yang ada sebelum membuat markup sendiri.
- Buat komponen dengan `php artisan make:livewire ... --no-interaction`.
- Jangan ubah kontrak API Tahap 1 dan jangan duplikasi logika LLM: Livewire memanggil `LlmClient` langsung
  (bukan HTTP ke `/api/chat-ai` milik sendiri).

## Penyesuaian terhadap teks tugas

| Teks tugas | Di proyek ini |
| --- | --- |
| "Login melalui Breeze" | Sudah ada Fortify + Livewire. Jangan pasang Breeze; cukup letakkan rute di bawah middleware `auth`. |
| "Simpan ke MySQL" | Proyek memakai SQLite. Kode tidak perlu berubah; pindah ke MySQL hanya soal `.env`. **Tanyakan pengguna** apakah mau diganti sebelum demo, jangan ganti sendiri. |

## Spesifikasi wajib

1. **Rute** — `GET /chat` bernama `chat`, di dalam grup `['auth', 'verified']` di `routes/web.php`.
   Tambahkan tautan "Chat" ke navigasi sidebar aplikasi yang sudah ada.
2. **Dua komponen yang berinteraksi lewat event**
   - `ChatSidebar`: daftar `ChatSession` milik user (terbaru di atas) dan tombol "Chat baru".
     Klik sesi → `dispatch('session-selected', sessionId: ...)`.
   - `ChatBox`: balon percakapan + form prompt. Mendengarkan `session-selected` dengan `#[On]`.
     Setelah membuat sesi baru saat pesan pertama dikirim → `dispatch('session-created')` supaya
     `ChatSidebar` memuat ulang daftarnya (`#[On('session-created')]`).
   - Tata letak split-panel: sidebar di kiri, chat di kanan; di layar sempit sidebar turun/tersembunyi.
3. **Keterikatan ke user** — setiap query sesi/pesan dibatasi lewat `auth()->user()->chatSessions()`.
   ID sesi datang dari browser, jadi **validasi ulang kepemilikan di server pada setiap aksi**
   (pilih sesi, kirim pesan). Sesi milik orang lain → 403/404.
4. **Kirim pesan** (`kirim()`)
   - Validasi `prompt`: `required|min:3|max:1000`, pesan error berbahasa Indonesia, tampil di bawah input via `@error`.
   - Simpan pesan `user` → kosongkan input → panggil `LlmClient` dengan riwayat sesi → simpan pesan `assistant`
     (isi `model` dan `tokens_used`).
   - Kegagalan LLM ditangkap: tampilkan balon/pesan error ramah, jangan crash.
5. **Loading state** — form memakai `wire:submit`; tombol kirim `wire:loading.attr="disabled"` dengan
   teks berganti ("Kirim" ↔ "Memikirkan jawaban..."), ditargetkan ke aksi `kirim` (`wire:target`).
   Input juga dikunci selama proses agar tidak ada submit ganda.
6. **Riwayat** — muat dari database di `mount()`/saat sesi dipilih, bukan di `render()`. Jangan simpan seluruh
   riwayat tanpa batas di properti publik: tampilkan N pesan terakhir (mis. 20). Refresh browser tidak boleh
   menghilangkan percakapan.
7. **Penghitung karakter** — tampilkan `x/1000` yang berubah saat mengetik. Lakukan di sisi klien dengan Alpine
   (`$wire.prompt.length`), bukan `wire:model.live` + `updatedPrompt()`, supaya tidak ada request per ketukan.
8. **Tampilan** — balon user dan assistant kontras dan jelas bedanya, auto-scroll ke pesan terbaru,
   mendukung dark mode yang sudah ada di proyek, bisa dipakai dengan keyboard (Enter kirim, label pada input).

## Challenge A+

Rubrik meminta minimal satu. Kerjakan **Challenge 1**; dua lainnya hanya jika pengguna memintanya.

1. **Teks mengalir (wajib dikerjakan)** — pakai streaming Livewire (`$this->stream(...)` + `wire:stream`)
   dengan method streaming `LlmClient` dari Tahap 1, sehingga jawaban muncul bertahap secara nyata, bukan
   animasi palsu. Simpan jawaban utuh ke database setelah stream selesai. Jika stream gagal di tengah,
   simpan bagian yang sudah diterima dan tampilkan pemberitahuan.
2. **Code visualizer + syntax highlight (opsional)** — panel kanan menampilkan blok kode (` ``` `) dari jawaban
   terakhir dalam `<pre>`. Syntax highlighting butuh library JS baru (mis. highlight.js) → **minta persetujuan
   pengguna dulu** (aturan `AGENTS.md` soal dependensi). Tanpa persetujuan, cukup `<pre>` monospaced.
3. **`wire:poll` log status (opsional)** — hanya masuk akal jika ada pekerjaan latar belakang (job) yang dipantau.
   Proyek belum punya; jangan membuat job tiruan hanya demi polling tanpa diminta.

## Tes (Pest + `Livewire::test`, `Http::fake`)

- Tamu yang membuka `/chat` dialihkan ke login.
- `kirim` dengan prompt kosong / < 3 / > 1000 karakter → error validasi, tidak ada baris baru.
- `kirim` sukses → dua baris `chat_messages` (user + assistant) pada sesi milik user, input kosong kembali,
  event `session-created` terkirim untuk sesi baru.
- Memilih sesi milik user lain → ditolak, pesannya tidak pernah dirender.
- Sidebar hanya menampilkan sesi milik user yang login.
- LLM gagal → komponen tetap render dengan pesan error.

Jalankan: `php artisan test --compact tests/Feature/Chat`, lalu seluruh suite sekali di akhir.

## Verifikasi di browser

Jalankan aplikasi (`composer run dev`), login, lalu cek sendiri: kirim pesan, tombol terkunci saat proses,
teks mengalir, refresh tidak menghapus riwayat, pindah sesi lewat sidebar, tampilan mobile dan dark mode.
Jika perubahan tampilan tidak muncul, jalankan `npm run build`. Butuh Ollama hidup — kalau tidak tersedia,
laporkan ke pengguna bahwa uji end-to-end belum dilakukan.

## Dokumentasi

Lengkapi `README.md` root (jangan hapus API Contract Tahap 1): panduan instalasi lokal, diagram alur Livewire
(Mermaid: input → `ChatBox::kirim` → `LlmClient` → DB → event → `ChatSidebar`), dan bagian tangkapan layar.
Tangkapan layar diambil pengguna; sediakan tempatnya dan beri tahu mereka.

## Di luar lingkup

Hapus/ubah nama sesi, pencarian riwayat, WebSockets/Echo, tool calling (materi Pertemuan 13), unggah file.

## Definisi Selesai

- [ ] `/chat` hanya bisa diakses setelah login dan seluruh data terikat ke `user_id`.
- [ ] Dua komponen berkomunikasi lewat `dispatch` + `#[On]`.
- [ ] Tombol kirim terkunci selama proses (`wire:loading`).
- [ ] Prompt dan jawaban tersimpan berurutan di `chat_messages` dan bertahan setelah refresh.
- [ ] Challenge 1 berjalan.
- [ ] Seluruh tes lulus; `vendor/bin/pint --dirty --format agent` bersih.
- [ ] README diperbarui; pengguna diberi tahu soal tangkapan layar, pilihan MySQL, dan langkah git/GitHub yang masih harus mereka lakukan.
