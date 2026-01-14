<?php

namespace DarkPeople\TelegramBot\Artisan;

use Telegram\Bot\Api;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Telegram\Bot\Exceptions\TelegramBotNotFoundException;
use Throwable;

class SyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:sync
                {bot? : The bot name defined in the config file}
                {--all : Sync all configured bots.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Telegram bot configuration (webhook, settings) for a specific bot or all bots';

    protected Api $telegram;

    protected BotsManager $botsManager;

    /** @var array Bot Config */
    protected array $config = [];

    /**
     * Execute the console command.
     *
     * @throws TelegramSDKException
     */
    public function handle(BotsManager $botsManager): int
    {
        $this->botsManager = $botsManager;

        $this->printHeader();

        // [1/3] Validate inputs and resolve bots to process
        $bots = $this->resolveBotsToSync();
        if ($bots === null) {
            // Validation already printed the reason
            return self::FAILURE;
        }

        // [2/3] Process loop
        $results = $this->syncBots($bots);

        // [3/3] Summary table
        $this->printSummary($results);

        return self::SUCCESS;

    }

    /**
     * Prints a nice header block.
     */
    protected function printHeader(): void
    {
        $this->line('<fg=cyan>' . str_repeat('━', 60) . '</>');
        $this->line('<fg=cyan;options=bold>Telegram Sync</>');
        $this->line('<fg=cyan>Command :</> telegram:sync');
        $this->line('<fg=cyan>Time    :</> ' . now()->toDateTimeString());
        $this->line('<fg=cyan>' . str_repeat('━', 60) . '</>');
    }

    /**
     * Step [1/3] Validate argument/option and return bots config to process.
     *
     * Return value:
     * - array<string, array> bots keyed by bot name, or
     * - null if validation fails (message already printed)
     */
    protected function resolveBotsToSync(): ?array
    {
        $botArg = $this->argument('bot');
        $all = (bool) $this->option('all');

        $this->line('');
        $this->line('<fg=blue;options=bold>[1/3] Validating input...</>');
        $this->line(' • Bot argument : <fg=cyan>' . ($botArg ?: '(none)') . '</>');
        $this->line(' • Option --all : ' . ($all
            ? '<fg=yellow;options=bold>ON</>'
            : '<fg=default>OFF</>'
        ));

        $allBots = $this->botsManager->getConfig("bots");

        if ($all) {
            if ($botArg) {
                $this->warn('--all detected. Bot argument will be ignored.');
            }

            $this->line(' → Loading all bots from configuration...');

            if (!is_array($allBots) || empty($allBots)) {
                $this->error(' No bots configured (config is null/empty).');
                $this->line(' ↳ Nothing to sync. Exiting.');
                return null;
            }

            $this->info(' Found: ' . count($allBots) . ' bot(s). Proceeding.');
            return $allBots;
        }

        // not --all
        if (!$botArg) {
            $this->error('You must provide a bot argument or use --all.');
            $this->line('<fg=yellow>↳ Examples:</>');
            $this->line('  <fg=cyan>php artisan telegram:sync mybot</>');
            $this->line('  <fg=cyan>php artisan telegram:sync --all</>');
            return null;
        }

        $this->line(sprintf(' → Looking for bot "%s" in configuration...', $botArg));

        if (!is_array($allBots) || !array_key_exists($botArg, $allBots)) {
            $this->error(' Bot not found in config.');
            $this->line(' ↳ Please register it under config("telegram.bots.' . $botArg . '").');
            return null;
        }

        $this->info(' Bot found. Proceeding.');
        return [$botArg => $allBots[$botArg]];
    }

    /**
     * Step [2/3] Sync all resolved bots.
     *
     * Returns an array of result rows suitable for table output.
     *
     * Result row structure:
     * [
     *   'bot' => string,
     *   'status' => 'SUCCESS'|'FAILED'|'SKIPPED',
     *   'webhook' => string|null,
     *   'reason' => string,
     * ]
     */
    protected function syncBots(array $bots): array
    {
        $this->info('[2/3] Sync process...');
        $results = [];

        foreach ($bots as $botKey => $botConfig) {
            $this->line('');
            $this->line(str_repeat('─', 60));
            $this->info("Bot: {$botKey}");
            $this->line(str_repeat('─', 60));

            // [2.1] Check webhook_url
            $this->line('[2.1] Checking webhook_url...');
            $webhookUrl = data_get($botConfig, 'webhook_url', null);

            // IMPORTANT: This follows your stated logic:
            // If webhook_url exists -> skip this bot

            if (!empty($webhookUrl) && Str::isUrl($webhookUrl, ["http", "https"])) {
                $this->warn(' webhook_url exists in config.'.$webhookUrl);
                $this->line(' ↳ Rule: this bot will be skipped.');
                $results[] = $this->makeResultRow($botKey, 'SKIPPED', null, 'webhook_url exists in config (skipped)');
                continue;
            }

            $this->line(' webhook_url not set.');

            // [2.2] Build webhook from base + path
            $this->line('[2.2] Checking webhook_base_url & webhook_url_path...');
            $base = trim((string) data_get($botConfig, 'webhook_base_url', ''));
            $path = trim((string) data_get($botConfig, 'webhook_url_path', ''));

            if ($base === '' || $path === '') {
                $this->error(' Invalid webhook config: webhook_base_url and/or webhook_url_path is empty.');
                $this->line(' ↳ Skipping this bot and continuing...');
                $results[] = $this->makeResultRow($botKey, 'SKIPPED', null, 'missing webhook_base_url or webhook_url_path');
                continue;
            }

            $finalWebhookUrl = rtrim($base, '/') . '/'.Str::kebab($botKey).'/' . ltrim($path, '/');

            if (! str_starts_with($finalWebhookUrl, 'https://')) {
                $this->line('<fg=red;options=bold>INVALID</> Webhook URL must use HTTPS.');
                $this->line(' ↳ Given: <fg=cyan>' . $finalWebhookUrl . '</>');
                $this->line('<fg=yellow>↳ Bot will be skipped.</>');

                $results[] = $this->makeResultRow(
                    $botKey,
                    'SKIPPED',
                    $finalWebhookUrl,
                    'webhook URL is not HTTPS'
                );

                continue;
            }

            $this->info(' Webhook URL resolved:');
            $this->line(' ↳ ' . $finalWebhookUrl);

            // [2.3] Register webhook
            $this->line('[2.3] Registering webhook...');
            try {
                $response = $this->registerWebhook($botKey, $botConfig, $finalWebhookUrl);

                // Normalize response details for logging
                $ok = (bool) ($response['ok'] ?? false);
                $http = (string) ($response['http_status'] ?? '-');
                $msg = (string) ($response['message'] ?? '');

                if ($ok) {
                    $this->info(" Webhook registered successfully. (HTTP {$http})");
                    $results[] = $this->makeResultRow($botKey, 'SUCCESS', $finalWebhookUrl, "Registered (HTTP {$http})");
                } else {
                    $this->error(" Failed to register webhook. (HTTP {$http})");
                    if ($msg !== '') {
                        $this->line(' ↳ Message: ' . $msg);
                    }
                    $results[] = $this->makeResultRow($botKey, 'FAILED', $finalWebhookUrl, $msg !== '' ? $msg : "Failed (HTTP {$http})");
                }
            } catch (Throwable $e) {
                $this->error(' Exception while registering webhook.');
                $this->line(' ↳ ' . $e->getMessage());
                $results[] = $this->makeResultRow($botKey, 'FAILED', $finalWebhookUrl, 'Exception: ' . $e->getMessage());
                continue;
            }
        }

        return $results;
    }

    /**
     * Builds a result row for the summary table.
     */
    protected function makeResultRow(string $bot, string $status, ?string $webhook, string $reason): array
    {
        return [
            'bot' => $bot,
            'status' => $status,
            'webhook' => $webhook ?: '-',
            'reason' => $reason,
        ];
    }

    /**
     * Step [3/3] Print summary table.
     */
    protected function printSummary(array $results): void
    {
        $this->line('');
        $this->line(str_repeat('━', 60));
        $this->info('[3/3] Sync summary');
        $this->line(str_repeat('━', 60));

        $headers = ['Bot', 'Status', 'Webhook', 'Reason'];
        $rows = array_map(fn ($r) => [$r['bot'], $r['status'], $r['webhook'], $r['reason']], $results);

        $this->table($headers, $rows);

        $total = count($results);
        $success = count(array_filter($results, fn ($r) => $r['status'] === 'SUCCESS'));
        $failed = count(array_filter($results, fn ($r) => $r['status'] === 'FAILED'));
        $skipped = count(array_filter($results, fn ($r) => $r['status'] === 'SKIPPED'));

        $this->line("Total: {$total} | Success: {$success} | Failed: {$failed} | Skipped: {$skipped}");
        $this->info('Done.');
    }

    private function resolveTelegramBot(?string $bot): void
    {
        try {
            $this->telegram = $this->botsManager->bot($bot);
        } catch (TelegramBotNotFoundException $e) {
            $this->warn($e->getMessage());
            $this->warn('You must specify a proper bot name or configure one.');
            $this->newLine();
            $this->info('💡Omitting the bot name will fallback to the default bot.');

            exit(1);
        }
    }

    protected function registerWebhook(string $botKey, array $botConfig, string $url): array
    {
        $this->resolveTelegramBot($botKey);

        $params = ['url' => $url];
        $certificatePath = data_get($botConfig, 'certificate_path', false);

        if ($certificatePath && $certificatePath !== 'YOUR-CERTIFICATE-PATH') {
            $params['certificate'] = $certificatePath;
        }

        $allowedUpdates = data_get($botConfig, 'allowed_updates');
        if ($allowedUpdates) {
            $params['allowed_updates'] = $allowedUpdates;
        }

        $response = $this->telegram->setWebhook($params);
        if ($response) {
            return [
                'ok' => true,
                'http_status' => 200,
                'message' => 'Success: Your webhook has been set!',
            ];
        }

        return [
            'ok' => false,
            'http_status' => 400,
            'message' => 'Your webhook could not be set!',
        ];
    }
}
