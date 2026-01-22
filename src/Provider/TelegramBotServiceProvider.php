<?php

namespace DarkPeople\TelegramBot\Provider;

use DarkPeople\TelegramBot\Artisan\MakeCommandCommand;
use DarkPeople\TelegramBot\Artisan\MakeMiddlewareCommand;
use DarkPeople\TelegramBot\Artisan\SyncCommand;
use DarkPeople\TelegramBot\Artisan\WebhookCommand;
use DarkPeople\TelegramBot\BotsManagerPlus;
use DarkPeople\TelegramBot\Commands\Compile\CommandConfigBuilder;
use DarkPeople\TelegramBot\Middleware\Compile\MiddlewareConfigBuilder;
use DarkPeople\TelegramBot\Reply\CacheReplyListenerStore;
use DarkPeople\TelegramBot\Reply\ReplyListenerStore;
use DarkPeople\TelegramBot\Support\UpdateMeta\Permissions\PermissionCatalog;
use DarkPeople\TelegramBot\Support\UpdateMeta\Permissions\PermissionResolver;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\BotsManager;

final class TelegramBotServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->configure();
        $this->offerPublishing();
        $this->registerBindings();
        $this->registerCommands();
    }

    /**
     * Setup the configuration.
     */
    private function configure(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/telegram.php', 'telegram');
        $command = $this->app->make(CommandConfigBuilder::class)->build();
        config(["telegram" => $command]);

        $compiled = $this->app->make(MiddlewareConfigBuilder::class)->build();
        config(['telegram.middleware_class' => $compiled]);
    }

    /**
     * Setup the resource publishing groups.
     */
    private function offerPublishing(): void
    {
        // Laravel
        if (class_exists(\Illuminate\Foundation\Application::class)) {
            if ($this->app instanceof \Illuminate\Foundation\Application) {
                if ($this->app->runningInConsole()) {
                    $this->publishes([
                        __DIR__ . '/../config/telegram.php' => config_path('telegram.php'),
                    ], 'telegram-bot-config');
                }
                return;
            }
        }

        if (class_exists(\Laravel\Lumen\Application::class)) {
            if ($this->app instanceof \Laravel\Lumen\Application) {
                $this->app->configure('telegram-bot');
            }
        }

    }

    /**
     * Register bindings in the container.
     */
    private function registerBindings(): void
    {
        // $this->app->singleton(BotsManagerPlus::class, static fn ($app): BotsManagerPlus => (new BotsManagerPlus(new BotsManager(config('telegram')))));

        $this->app->singleton(BotsManagerPlus::class, function ($app) {
            // Ambil instance BotsManager yang sama dengan Facade Telegram
            $botsManager = $app->make(BotsManager::class); // atau $app->make('telegram')

            return (new BotsManagerPlus($botsManager));
        });
        $this->app->alias(BotsManagerPlus::class, 'telegram-bot');

        $this->app->singleton(PermissionCatalog::class, fn () => new PermissionCatalog());
        $this->app->singleton(PermissionResolver::class, fn ($app) => new PermissionResolver($app->make(PermissionCatalog::class)));

        $this->app->singleton(ReplyListenerStore::class, fn () => new CacheReplyListenerStore());
    }

    /**
     * Register the Artisan commands.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                WebhookCommand::class,
                SyncCommand::class,
                MakeCommandCommand::class,
                MakeMiddlewareCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [BotsManagerPlus::class, 'telegram-bot'];
    }
}
