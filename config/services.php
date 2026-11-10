<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'llm' => [
        'timeout' => (int) env('LLM_TIMEOUT', 120),
        'system_prompt' => env('LLM_SYSTEM_PROMPT', 'Anda adalah asisten karier untuk pencari kerja di Indonesia. Bantu pengguna memperbaiki CV dan surat lamaran, menyiapkan jawaban wawancara, menganalisis kecocokan dengan lowongan, dan menyusun rencana pengembangan skill. Jawab dalam bahasa Indonesia yang ringkas dan praktis, dengan langkah konkret. Jika informasi kurang (posisi yang dilamar, pengalaman, isi lowongan), tanyakan dulu. Jangan mengarang pengalaman, gelar, atau data gaji; jika tidak tahu, katakan terus terang. Tolak dengan sopan permintaan di luar topik karier.'),
        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'qwen3:14b'),
        ],
        'senopati' => [
            'url' => env('SENOPATI_URL'),
            'token' => env('SENOPATI_TOKEN'),
            'api_key' => env('SENOPATI_API_KEY'),
            'model' => env('SENOPATI_MODEL', 'qwen3:14b'),
        ],
    ],

];
