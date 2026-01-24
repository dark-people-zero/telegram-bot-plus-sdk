<?php

return [
    'cmd' => [
        'not_found' => "Perintah `{requested}` tidak ditemukan",
        'did_you_mean' => "Mungkin maksud kamu: {suggest}?",
        'try_help' => "Coba: `{cmd} --help`",
    ],

    'help' => [
        'root' => [
            'title' => "*Daftar Perintah*",
        ],
        'group' => [
            'title' => "*Daftar Perintah yang tersedia untuk* `{cmd}`:",
        ],
        'leaf' => [
            'title' => "*Perintah:* `{cmd}`",
        ],
        
        'subcommands' => "*Subcommand:*",
        'usage' => "*Cara pakai:*",
        'args' => "*Argument:*",
        'opts' => "*Option:*",
        'global_opts' => "*Global option:*",
        'description' => "*Keterangan:*",
    ],

    'arg' => [
        'missing' => "Argument kurang: {items}",
        'too_many' => "Terlalu banyak argument.",
        'invalid' => "Argument tidak valid: {items}",    
    ],

    'opt' => [
        'missing' => "Option wajib belum ada: {items}",
        'invalid' => "Value option tidak valid: {items}",
    ],
    'unauthorize' => [
        'title' => "**Akses Ditolak**",
        'message' => "Kamu tidak memiliki izin untuk menjalankan perintah ini.",
    ],
    
    'ok' => "",

    /*
    |--------------------------------------------------------------------------
    | Interactive Prompt (Listen Reply)
    |--------------------------------------------------------------------------
    |
    | Used by Command Inspector when a command is missing required arguments
    | or required options. The renderer will store a pending state and ask
    | the user for the next value step-by-step.
    |
    | - prompt.default: fallback template when a specific key prompt is not found.
    | - hint.cancel: hint text shown under the prompt message.
    |
    | Variables:
    | - {type}: "argument" or "option"
    | - {text}: prompt key (e.g. "name", "age")
    |
    */
    'prompt' => [
        'default' => "Masukkan nilai untuk {type} *`{text}`*:",
    ],

    'hint' => [
        'cancel' => "_Ketik `cancel` untuk membatalkan._",
    ],

];