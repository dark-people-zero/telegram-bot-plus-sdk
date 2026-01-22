<?php

return [
    'cmd' => [
        'not_found' => "Perintah tidak ditemukan: `{requested}`",
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
];