# Instruksi AI — Tahap 1: Secure LLM Chat API Hub (Pertemuan 11)

Kerjakan file ini sampai seluruh "Definisi Selesai" terpenuhi. Setelah itu baru lanjut ke
[02-pertemuan-12-livewire-chat.md](02-pertemuan-12-livewire-chat.md). Jangan mengerjakan UI chat di tahap ini.

## Tujuan

Backend API terproteksi Sanctum yang menerima prompt, memanggil LLM (Ollama lokal, fallback Senopati AI ITS),
menyimpan riwayat ke database, dan mengembalikan JSON lewat API Resource.

## Kondisi proyek saat instruksi ini ditulis (verifikasi ulang sebelum mulai)

- Laravel 13, PHP 8.5, Livewire 4, Flux UI 2, Fortify (login/registrasi sudah ada), Pest 5, Tailwind 4.
- Database: mysql. Model yang ada hanya `User`. Belum ada `routes/api.php` dan Sanctum belum terpasang.
- Ikuti `AGENTS.md`: buat file dengan `php artisan make:* --no-interaction`, aktifkan skill
  `laravel-best-practices` dan `testing-best-practices`, jalankan `vendor/bin/pint --dirty --format agent` di akhir.

## Keputusan yang sudah diambil (jangan didesain ulang)

| Hal | Keputusan |
| --- | --- |
| Sanctum | Pasang dengan `php artisan install:api --no-interaction`. Dependensi ini sudah disetujui lewat dokumen ini. Tambahkan trait `HasApiTokens` ke `User`. |
| Skema DB | Dua tabel, dipakai lagi di Tahap 2: `chat_sessions` dan `chat_messages` (satu baris per pesan, bukan per pasangan tanya-jawab). |
| Pemanggilan LLM | Satu kelas `App\Services\LlmClient`. Controller (Tahap 1) dan Livewire (Tahap 2) sama-sama memanggil kelas ini. Tidak ada `Http::post` ke LLM di tempat lain. |
| Konfigurasi | Semua URL, nama model, token, dan system prompt di `config/services.php` kunci `llm`, dibaca dari `.env`. Tidak ada URL yang di-hardcode. Tambahkan kuncinya ke `.env.example`. |
| Endpoint Ollama | `POST {OLLAMA_URL}/api/chat` dengan `messages` (bukan `/api/generate`), supaya system prompt dan riwayat bisa dikirim. |

### Skema

`chat_sessions`: `id`, `user_id` (FK, cascade delete), `title` (string), timestamps.

`chat_messages`: `id`, `chat_session_id` (FK, cascade delete), `user_id` (FK), `role` (`user` | `assistant`),
`content` (text), `model` (nullable string), `tokens_used` (nullable unsigned integer), timestamps.

- `model` dan `tokens_used` diisi hanya pada baris `assistant`.
- `tokens_used` = `prompt_eval_count + eval_count` dari respons Ollama; `null` jika penyedia tidak mengirim angka.
- Buat model `ChatSession` dan `ChatMessage` beserta factory dan relasinya (`User hasMany ChatSession`,
  `ChatSession hasMany ChatMessage`).

### Variabel `.env`

```
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=qwen3:14b
SENOPATI_URL=https://senopati.its.ac.id/llm-gateway/v1
SENOPATI_TOKEN=
SENOPATI_API_KEY=
SENOPATI_MODEL=qwen3:14b
LLM_SYSTEM_PROMPT="Anda adalah asisten karier untuk pencari kerja di Indonesia. Bantu pengguna memperbaiki CV dan surat lamaran, menyiapkan jawaban wawancara, menganalisis kecocokan dengan lowongan, dan menyusun rencana pengembangan skill. Jawab dalam bahasa Indonesia yang ringkas dan praktis, dengan langkah konkret. Jika informasi kurang (posisi yang dilamar, pengalaman, isi lowongan), tanyakan dulu. Jangan mengarang pengalaman, gelar, atau data gaji; jika tidak tahu, katakan terus terang. Tolak dengan sopan permintaan di luar topik karier."
```

Tema kelompok: **asisten AI job-seeker**. Nilai `LLM_SYSTEM_PROMPT` harus satu baris dan tidak boleh memuat
tanda kutip ganda di dalamnya. Pakai nilai yang sama sebagai default di `config/services.php` dan `.env.example`.

## Spesifikasi wajib

1. **Login token** — `POST /api/login` (publik): validasi `email` dan `password`, kembalikan
   `{"access_token": "...", "token_type": "Bearer"}`; kredensial salah → 401 JSON. Batasi dengan `throttle`.
2. **Chat** — `POST /api/chat-ai` di dalam grup `auth:sanctum`.
   - Validasi lewat FormRequest `ChatAiRequest`: `prompt` wajib, string, maksimal 1000 karakter;
     `session_id` opsional, integer, dan **harus milik user yang sedang login** (kalau bukan → 403/422, jangan bocorkan sesi orang lain).
   - Tanpa `session_id`: buat `ChatSession` baru, `title` = potongan awal prompt.
   - Simpan pesan `user`, panggil `LlmClient` dengan riwayat sesi + system prompt, simpan pesan `assistant`.
   - Respons dibungkus `ChatResource` (API Resource), bentuk persisnya:

   ```json
   {
     "status": "success",
     "data": {
       "session_id": 12,
       "model_used": "qwen3:8b",
       "prompt": "Jelaskan middleware!",
       "ai_response": "Middleware adalah...",
       "timestamp": "2026-08-28 09:30:00"
     }
   }
   ```

   - LLM gagal total (Ollama dan fallback mati): log dengan `Log::error`, kembalikan JSON
     `{"status": "error", "message": "..."}` dengan status 503. Pesan `user` tetap tersimpan. Aplikasi tidak boleh crash.
3. **`LlmClient`** — satu method publik untuk chat biasa, menerima array `messages`, mengembalikan
   `array{content: string, model: string, tokens: int|null}`. Selalu pakai `Http::timeout(...)`.

## Challenge A+ (kerjakan ketiganya, urut dari yang termurah)

1. **System prompt** — `LlmClient` selalu menyisipkan `config('services.llm.system_prompt')` sebagai pesan
   `system` pertama. Tema kelompok cukup diganti lewat `.env`.
2. **Fallback Senopati** — jika panggilan Ollama melempar exception koneksi/timeout atau status gagal,
   `Log::warning` lalu coba Senopati. Lewati fallback bila `SENOPATI_URL` kosong.
   **Format API Senopati belum diverifikasi**: slide hanya memberi contoh `POST https://senopati.its.ac.id/api/chat`
   dengan `prompt` → `result`. Isolasi pemetaan request/respons Senopati dalam satu method privat dan tandai
   dengan komentar `shortcut:` agar mudah dikoreksi setelah pengguna memberi dokumentasi aslinya. Jangan mengarang lebih jauh dari itu.
3. **Streaming SSE** — `POST /api/chat-ai/stream` (juga di bawah `auth:sanctum`, FormRequest yang sama),
   `response()->stream(...)` dengan header `Content-Type: text/event-stream` dan `Cache-Control: no-cache`.
   Teruskan potongan `message.content` dari Ollama (`stream: true`, satu objek JSON per baris) sebagai
   `data: ...\n\n`. Setelah stream selesai, simpan pesan `assistant` utuh ke database. Tambahkan method
   streaming di `LlmClient` (menerima callback per potongan) karena Tahap 2 akan memakainya lagi.

## Tes (Pest, pakai `Http::fake`, jangan memanggil Ollama sungguhan)

- Tanpa token → 401.
- Prompt kosong / lebih dari 1000 karakter → 422.
- Sukses → struktur JSON sesuai spesifikasi, dua baris `chat_messages` tersimpan, `model` dan `tokens_used` terisi.
- `session_id` milik user lain → ditolak.
- Ollama gagal + Senopati sukses → respons sukses dari fallback.
- Semua penyedia gagal → 503, tidak ada exception bocor.
- Login: kredensial benar mengembalikan token, salah → 401.

Jalankan: `php artisan test --compact tests/Feature/Api`.

## Dokumentasi

Buat `README.md` di root berisi API Contract: URL, header (`Authorization: Bearer`, `Accept: application/json`),
parameter request, contoh respons sukses/401/422/503 untuk ketiga endpoint, plus cara menjalankan Ollama
(`ollama pull qwen3:14b`, `ollama serve`). Ini diminta eksplisit oleh tugas.

## Di luar lingkup tahap ini

UI/Livewire, CRUD sesi lewat API, token abilities, kedaluwarsa token, retry berlapis, antrean/job.

## Definisi Selesai

- [ ] `php artisan route:list --path=api` menampilkan `login`, `chat-ai`, `chat-ai/stream`.
- [ ] Seluruh tes di atas lulus; tes bawaan proyek tetap lulus.
- [ ] `vendor/bin/pint --dirty --format agent` bersih.
- [ ] `README.md` berisi API Contract.
- [ ] `.env` tidak ikut ter-commit (cek `.gitignore`; proyek ini belum menjadi repo git — `git init` dan push adalah keputusan pengguna, jangan lakukan sendiri).
- [ ] Laporkan ke pengguna hal yang belum bisa diverifikasi: uji manual dengan Ollama hidup (Postman) dan format API Senopati.
