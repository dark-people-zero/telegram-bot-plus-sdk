<?php

namespace DarkPeople\TelegramBot\Artisan;

use DarkPeople\TelegramBot\Commands\Inspect\ConsoleCommandRegistry;
use DarkPeople\TelegramBot\Support\ConsoleI18n;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TelegramCacheClearCommand extends Command
{
    protected $signature = 'telegram:cache:clear';

    protected $description = 'Clear all Telegram SDK internal caches';

    public function handle(): int
    {
        $cleared = [];

        // 1) Command registry
        Cache::forget(ConsoleCommandRegistry::CACHE_KEY);
        $cleared[] = ConsoleCommandRegistry::CACHE_KEY;

        // 2) Middleware config
        Cache::forget('telegram:config_middleware');
        $cleared[] = 'telegram:config_middleware';

        // 3) Command config
        Cache::forget('telegram:config_commands');
        $cleared[] = 'telegram:config_commands';
        
        // 4) Console i18n (per language)
        ConsoleI18n::clearCache();
        $cleared[] = 'telegram:locale:* (all languages)';

        $this->info('Telegram SDK cache cleared:');

        foreach ($cleared as $key) {
            $this->line(" - {$key}");
        }

        return self::SUCCESS;
    }

}
