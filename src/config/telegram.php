<?php

use Telegram\Bot\Commands\HelpCommand;

return [
    /*
    |--------------------------------------------------------------------------
    | Your Telegram Bots
    |--------------------------------------------------------------------------
    | You can run multiple bots at the same time via BotsManager.
    | Each bot you own is defined here.
    |
    | Supported Params (per bot):
    |
    | - token: Bot token from BotFather.
    |          https://core.telegram.org/bots#botfather
    |
    | - certificate_path: (Optional) Certificate for setWebhook (if needed).
    |
    | - webhook_url: (Optional) Full webhook URL.
    |               If provided, it will be used directly.
    |
    | - webhook_base_url + webhook_url_path: (Optional) Alternative if you want to
    |               build the webhook URL.
    |               If webhook_url is empty, the URL will be built from base + path.
    |               Note: BotNameResolver uses this configuration to resolve the
    |               botName from the incoming URL.
    |
    | - allowed_updates: (Optional) Filter update types from Telegram.
    |               https://core.telegram.org/bots/api#update
    |
    | - commands: (Optional) List of commands specific to this bot.
    |               Supported Values:
    |               - "Command Group Name"
    |               - "Shared Command Name"
    |               - "Full Path to Command Class"
    |
    | - middleware: (Plus SDK) Bot-specific middleware authoring (STRICT).
    |               The format must be an event-map, and each item must be a
    |               class-string only.
    |               It does NOT support array rules like {class, forBot} inside
    |               bot middleware.
    |
    | Note:
    | - The final middleware output (from scan + config) will be compiled into
    |   `telegram.middleware_class`, and the runtime only reads that compiled
    |   result.
    |
    */
    'bots' => [
        'mybot' => [
            'token' => env('TELEGRAM_BOT_TOKEN', 'YOUR-BOT-TOKEN'),
            'certificate_path' => env('TELEGRAM_CERTIFICATE_PATH', 'YOUR-CERTIFICATE-PATH'),

            // If webhook_url is provided, it will be used directly.
            'webhook_url' => env('TELEGRAM_WEBHOOK_URL', 'YOUR-BOT-WEBHOOK-URL'),

            // If webhook_url is empty, the URL will be built from webhook_base_url + webhook_url_path.
            // This is also used to resolve botName from the URL.
            'webhook_base_url' => env('TELEGRAM_WEBHOOK_BASE_URL', 'YOUR-BOT-WEBHOOK-BASE-URL'),
            'webhook_url_path' => env('TELEGRAM_WEBHOOK_URL_PATH', '/webhook'),

            /*
             * Allowed updates from Telegram.
             * @see https://core.telegram.org/bots/api#update
             */
            'allowed_updates' => null,

            /*
             * Commands specific to this bot (optional).
             * Default (when empty): it will use the compiled global commands.
             */
            'commands' => [
                // \App\Telegram\Commands\StartCommand::class
            ],

            /*
             * (Plus SDK) Bot middleware authoring (STRICT).
             *
             * - Must be an event-map:
             *   [
             *     'message' => [MiddlewareClass::class, ...],
             *     'callback_query' => [...],
             *     'command' => [
             *        '__all__' => [...],
             *        'start' => [...],
             *     ]
             *   ]
             *
             * - Items must be class-string only (no array rules).
             * - Event groups (like 'callbacks_payments') may be used as shortcuts
             *   if your compiler supports mapping group -> events.
             *
             * Runtime execution:
             * - For commands: run command.__all__ + command.<commandName>
             * - For non-commands: run middleware for the eventName
             */
            'middleware' => [
                // Example event group (optional, if used by your compiler)
                'callbacks_payments' => [], // group: ('callback_query', 'shipping_query', 'pre_checkout_query')

                // Example direct event
                'callback_query' => [],

                'message' => [
                    // \App\Telegram\Middleware\AuthMiddleware::class,
                ],

                'command' => [
                    '__all__' => [
                        // \App\Telegram\Middleware\LogMiddleware::class,
                    ],
                    'start' => [
                        // \App\Telegram\Middleware\StartOnlyMiddleware::class,
                    ],
                ],
            ],
        ],

        // 'mySecondBot' => [
        //     'token' => '123456:abc',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Middleware Authoring (Plus SDK)
    |--------------------------------------------------------------------------
    | Key: telegram.middleware
    |
    | This is global middleware for all bots, using an event-map format.
    |
    | Differences vs bot middleware:
    | - Global middleware supports:
    |   1) class-string items
    |   2) array rules: ['class' => ..., 'forBot' => ...]
    |
    | forBot on array rules:
    | - null   => all bots
    | - string => only a specific bot
    | - array  => multiple bots
    |
    | Notes:
    | - This is the "authoring config". The final output will be compiled into
    |   `telegram.middleware_class`.
    | - Runtime (BotsManagerPlus) only reads `telegram.middleware_class`.
    */
    'middleware' => [
        // Example event group (optional, if used by your compiler)
        'callbacks_payments' => [], // group: ('callback_query', 'shipping_query', 'pre_checkout_query')

        'callback_query' => [],

        'message' => [
            // Form 1: direct class string
            // \App\Telegram\Middleware\LogMiddleware::class,

            // Form 2: array rule (global only)
            // [
            //     'class' => \App\Telegram\Middleware\AuthMiddleware::class,
            //     'forBot' => 'mybot',
            // ],
        ],

        'command' => [
            '__all__' => [
                // \App\Telegram\Middleware\AuthMiddleware::class,
            ],
            'start' => [
                // [
                //     'class' => \App\Telegram\Middleware\StartOnlyMiddleware::class,
                //     'forBot' => ['mybot', 'mySecondBot'],
                // ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Bot Name
    |--------------------------------------------------------------------------
    | The default bot used when you call TelegramBot::bot() without a name.
    */
    'default' => 'mybot',

    /*
    |--------------------------------------------------------------------------
    | Asynchronous Requests [Optional]
    |--------------------------------------------------------------------------
    | When true, all requests to Telegram are made asynchronously (non-blocking).
    */
    'async_requests' => env('TELEGRAM_ASYNC_REQUESTS', false),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Handler [Optional]
    |--------------------------------------------------------------------------
    | Custom HTTP client handler.
    | Must implement: \Telegram\Bot\HttpClients\HttpClientInterface
    |
    | Default: Guzzle
    */
    'http_client_handler' => null,

    /*
    |--------------------------------------------------------------------------
    | Base Bot Url [Optional]
    |--------------------------------------------------------------------------
    | Custom Telegram API base URL (proxy/local bot api endpoint).
    |
    | Default: https://api.telegram.org/bot
    */
    'base_bot_url' => null,

    /*
    |--------------------------------------------------------------------------
    | Resolve Injected Dependencies in Commands [Optional]
    |--------------------------------------------------------------------------
    | When true, constructor dependencies in commands will be resolved by the
    | container.
    */
    'resolve_command_dependencies' => true,

    /*
    |--------------------------------------------------------------------------
    | Scan Paths (Plus SDK)
    |--------------------------------------------------------------------------
    | Directories scanned to discover command/middleware classes.
    |
    | - command_scan_paths: default app/Telegram/Commands
    | - middleware_scan_paths: default app/Telegram/Middleware
    */
    'command_scan_paths' => [
        app_path('Telegram/Commands'),
    ],

    'middleware_scan_paths' => [
        app_path('Telegram/Middleware'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Register Telegram Global Commands [Optional]
    |--------------------------------------------------------------------------
    | Global SDK commands that are active for all bots.
    | Default: HelpCommand (responds when the user sends /help)
    */
    'commands' => [
        HelpCommand::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Groups [Optional]
    |--------------------------------------------------------------------------
    | You can group commands to make them easier to assign to bots.
    |
    | Supported:
    | 1) Full command class path
    | 2) Shared command key
    | 3) Group name (group inside group)
    | 4) Any combination of the above
    */
    'command_groups' => [
        /* // Type 1
        'commmon' => [
            \App\Telegram\Commands\TodoCommand::class,
            \App\Telegram\Commands\TaskCommand::class,
        ],
        */

        /* // Type 2
        'subscription' => [
            'start', // Shared Command Name
            'stop',  // Shared Command Name
        ],
        */

        /* // Type 3
        'auth' => [
            \App\Telegram\Commands\LoginCommand::class,
            \App\Telegram\Commands\SomeCommand::class,
        ],

        'stats' => [
            \App\Telegram\Commands\UserStatsCommand::class,
            \App\Telegram\Commands\SubscriberStatsCommand::class,
            \App\Telegram\Commands\ReportsCommand::class,
        ],

        'admin' => [
            'auth',  // Command Group Name
            'stats', // Command Group Name
        ],
        */

        /* // Type 4
        'myBot' => [
            'admin',
            'subscription',
            'status', // shared command key
            \App\Telegram\Commands\BotCommand::class,
        ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared Commands [Optional]
    |--------------------------------------------------------------------------
    | Shared commands are a registry of commands that can be reused across bots.
    | They are not enabled automatically; you must reference the key in bot
    | commands or in a command group.
    */
    'shared_commands' => [
        // 'start' => \App\Telegram\Commands\StartCommand::class,
        // 'stop' => \App\Telegram\Commands\StopCommand::class,
        // 'status' => \App\Telegram\Commands\StatusCommand::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | NOTE: Compiled Outputs (Internal)
    |--------------------------------------------------------------------------
    | After the provider boot/register:
    |
    | - `telegram.middleware_class` will contain the compiled middleware output:
    |   telegram.middleware_class[botName][eventName] = [MiddlewareClass...]
    |   telegram.middleware_class[botName]['command']['__all__'] = [...]
    |   telegram.middleware_class[botName]['command'][commandName] = [...]
    |
    | - Runtime (BotsManagerPlus) only reads this structure.
    |
    | Do not edit this manually unless you truly understand the impact.
    */
];
