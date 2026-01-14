<?php

namespace DarkPeople\TelegramBot\Http\Controllers;

use DarkPeople\TelegramBot\Facades\TelegramBot;
use Illuminate\Http\Request;

final class WebhookController
{
    public function __invoke(Request $request)
    {
        // telegram-bot-sdk akan baca update dari request
        // dan jalankan command handler sesuai config telegram.php
        // Telegram::commandsHandler(true);
        TelegramBot::handler();
        return response()->json(['ok' => true]);
    }
}
