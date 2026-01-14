<?php

use Illuminate\Support\Facades\Route;
use DarkPeople\TelegramBot\Http\Controllers\WebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Str;
foreach (config("telegram.bots") as $bot => $config) {
    $webhookUrl = data_get($config, 'webhook_url');
    $path = null;
    if(!Str::isUrl($webhookUrl, ["http", "https"])) {
        $baseUrl = data_get($config, "webhook_base_url");
        $paths = data_get($config, "webhook_url_path");
        if(!empty($baseUrl) && !empty($paths)) {
            $paths = trim($paths, "/");
            $path = "$bot/$paths";
        }
    }


    if (!empty($path)) {
        Route::post(Str::kebab($path), WebhookController::class)->withoutMiddleware([VerifyCsrfToken::class]);
    }
}
