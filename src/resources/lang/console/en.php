<?php

return [
    'cmd' => [
        'not_found' => "Command not found: `{requested}`",
        'did_you_mean' => "Did you mean: {suggest}?",
        'try_help' => "Try: `{cmd} --help`",
    ],

    'help' => [
        'root' => [
            'title' => "*Available Commands*",
        ],
        'group' => [
            'title' => "*Available commands for the* `{cmd}` *namespace*:",
        ],
        'leaf' => [
            'title' => "*Command:* `{cmd}`",
        ],

        'subcommands' => "*Subcommands:*",
        'usage' => "*Usage:*",
        'args' => "*Arguments:*",
        'opts' => "*Options:*",
        'global_opts' => "*Global options:*",
        'description' => "*Description:*",
    ],

    'arg' => [ 
        'missing' => "Missing argument(s): {items}",
        'too_many' => "Too many arguments.",
        'invalid' => "Invalid argument(s): {items}",
    ],

    'opt' => [
        'missing' => "Missing required option(s): {items}",
        'invalid' => "Invalid option value: {items}",
    ],
    'unauthorize' => [
        'title' => "**Access Denied**",
        'message' => "You are not allowed to run this command.",
    ],
    
    'ok' => "",
];