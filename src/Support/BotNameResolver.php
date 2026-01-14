<?php

namespace DarkPeople\TelegramBot\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class BotNameResolver
{
    public function resolveFromRequest(Request $request, array $telegramConfig): ?string
    {
        $current = $this->normalizeUrl($request->fullUrl()); // include query; kalau gak mau query pakai url()

        $bots = (array) data_get($telegramConfig, 'bots', []);
        foreach ($bots as $name => $cfg) {
            $direct = data_get($cfg, 'webhook_url');
            if ($direct && $this->normalizeUrl($direct) === $current) {
                return (string) $name;
            }

            $base = data_get($cfg, 'webhook_base_url');
            $path = data_get($cfg, 'webhook_url_path', '/webhook');

            if ($base) {
                $built = rtrim($base, '/')
                    . '/'
                    . Str::kebab((string) $name)
                    . '/'
                    . ltrim((string) $path, '/');

                if ($this->normalizeUrl($built) === $current) {
                    return (string) $name;
                }
            }
        }

        return null;
    }

    private function normalizeUrl(string $url): string
    {
        // minimal normalize: trim spaces, remove trailing slash, keep scheme/host/path
        $url = trim($url);
        // hilangkan trailing slash
        $url = rtrim($url, '/');

        return $url;
    }
}
