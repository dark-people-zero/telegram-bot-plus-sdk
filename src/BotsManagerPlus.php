<?php

namespace DarkPeople\TelegramBot;

use DarkPeople\TelegramBot\Support\BotNameResolver;
use DarkPeople\TelegramBot\Support\StandardWebhookValidator;
use DarkPeople\TelegramBot\Support\TelegramContext;
use DarkPeople\TelegramBot\Support\UpdateMeta\TelegramUpdateAnalyzer;
use DarkPeople\TelegramBot\Support\UpdateMeta\TelegramUpdateMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\BotsManager;

final class BotsManagerPlus
{
    public function __construct(protected BotsManager $bots) {}

    public function __call($method, $args)
    {
        return $this->bots->$method(...$args);
    }

    public function handler(Request $request): void
    {
        $rootConfig = (array) Config::get('telegram', []);

        // 1) Resolve botName via URL match (validasi standar kamu)
        $botNameResolver = app()->make(BotNameResolver::class);
        $botName = $botNameResolver->resolveFromRequest($request, $rootConfig);

        if (!$botName) {
            Log::warning('[TelegramBotPlus] webhook hit but no bot matched by url', ['url' => $request->url()]);
            return;
        }

        $botConfig = (array) data_get($rootConfig, "bots.$botName", []);

        // 2) Read update (from SDK)
        $telegram = $this->bots->bot($botName);
        $update   = $telegram->getWebhookUpdate();
        $updateData = $update->all();

        // 3) Optional standard validation (secret token + minimal update_id)
        $std = app()->make(StandardWebhookValidator::class);
        if (!$std->validate($request, $botConfig, $updateData)) {
            Log::warning('[TelegramBotPlus] standard webhook validation failed', ['bot' => $botName]);
            return;
        }

        $ctx = new TelegramContext($telegram, $update, $botName);
        app()->instance(TelegramContext::class, $ctx);

        $meta = app(TelegramUpdateAnalyzer::class)->analyze($ctx);
        app()->instance(TelegramUpdateMeta::class, $meta);

        // run validasi
        $eventName = $ctx->eventName;
        $commandName = $ctx->commandName;
        $isCommand = $ctx->isCommand;

        $listClassMid = [];

        if ($isCommand) {
            $listClassMid = array_merge(
                $listClassMid,
                (array) data_get($rootConfig, "middleware_class.$botName.command.__all__", [])
            );

            if (!empty($commandName)) {
                $listClassMid = array_merge(
                    $listClassMid,
                    (array) data_get($rootConfig, "middleware_class.$botName.command.$commandName", [])
                );
            }
        } else {
            if (!empty($eventName)) {
                $listClassMid = array_merge(
                    $listClassMid,
                    (array) data_get($rootConfig, "middleware_class.$botName.$eventName", [])
                );
            }
        }

        $listClassMid = array_values(array_unique($listClassMid));

        // eksekusi middleware
        foreach ($listClassMid as $mwClass) {
            if (!is_string($mwClass) || $mwClass === '') continue;

            $mw = app()->make($mwClass);

            if ($mw->handle($ctx) !== true) {
                return; // stop
            }
        }


        $telegram->commandsHandler(true);

    }
}
