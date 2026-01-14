<?php

namespace DarkPeople\TelegramBot\Support;

use Illuminate\Http\Request;

final class StandardWebhookValidator
{
    public function validate(Request $request, array $botConfig, array $updateData): bool
    {
        // 1) minimal structure
        if (!array_key_exists('update_id', $updateData)) {
            return false;
        }

        // 2) optional secret token
        $secret = data_get($botConfig, 'secret_token');
        if ($secret) {
            $header = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if (!$header || !hash_equals((string) $secret, (string) $header)) {
                return false;
            }
        }

        return true;
    }
}
