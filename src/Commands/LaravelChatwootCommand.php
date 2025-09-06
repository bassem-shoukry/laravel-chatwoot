<?php

namespace BassamShoukry\LaravelChatwoot\Commands;

use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use BassamShoukry\LaravelChatwoot\Services\MessageService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class LaravelChatwootCommand extends Command
{
    public $signature = 'chatwoot 
                        {--status : Show package status and configuration}
                        {--accounts : List configured accounts}
                        {--stats : Show usage statistics}';
    public $description = 'Laravel Chatwoot package information and status';
    protected AccountManager $accountManager;
    protected MessageService $messageService;

    public function __construct(AccountManager $accountManager, MessageService $messageService)
    {
        parent::__construct();
        $this->accountManager = $accountManager;
        $this->messageService = $messageService;
    }

    public function handle(): int
    {
        $this->displayBanner();

        if ($this->option('status')) {
            $this->showPackageStatus();
        } elseif ($this->option('accounts')) {
            $this->showAccounts();
        } elseif ($this->option('stats')) {
            $this->showStatistics();
        } else {
            $this->showHelp();
        }

        return self::SUCCESS;
    }

    protected function displayBanner(): void
    {
        $this->info('');
        $this->info('   _____ _           _              _   ');
        $this->info('  / ____| |         | |            | |  ');
        $this->info(' | |    | |__   __ _| |___      __ | |_ ');
        $this->info(' | |    | \'_ \ / _` | __\ \ /\ / / | __|');
        $this->info(' | |____| | | | (_| | |_ \ V  V /  | |_ ');
        $this->info('  \_____|_| |_|\__,_|\__| \_/\_/    \__|');
        $this->info('');
        $this->info('  🚀 Laravel Chatwoot Package v1.0.0');
        $this->info('  📞 Multi-account template messaging for Chatwoot');
        $this->info('');
    }

    protected function showHelp(): void
    {
        $this->info('📚 Available Commands:');
        $this->newLine();

        $commands = [
            'chatwoot --status'                                             => 'Show package status and configuration',
            'chatwoot --accounts'                                           => 'List all configured accounts',
            'chatwoot --stats'                                              => 'Show usage statistics',
            'chatwoot:test-connection [account]'                            => 'Test API connections',
            'chatwoot:send-template {account} {inbox} {template} {contact}' => 'Send a template message',
            'chatwoot:sync-templates [account]'                             => 'Sync templates from various sources',
        ];

        foreach ($commands as $command => $description) {
            $this->info("  <comment>$command</comment>");
            $this->info("    $description");
            $this->newLine();
        }

        $this->info('📖 For detailed command help, use: <comment>php artisan help [command]</comment>');
        $this->newLine();

        $this->info('🔗 Useful Links:');
        $this->info('  • Documentation: https://github.com/bassamshoukry/laravel-chatwoot');
        $this->info('  • Chatwoot API: https://www.chatwoot.com/developers/api/');
        $this->newLine();
    }

    protected function showPackageStatus(): void
    {
        $this->info('📊 Package Status:');
        $this->newLine();

        // Configuration status
        $accounts = config('chatwoot.accounts', []);
        $queueConfig = config('chatwoot.queue', []);
        $webhookConfig = config('chatwoot.webhooks', []);

        $table = new Table($this->output);
        $table->setHeaders(['Component', 'Status', 'Details']);

        $table->addRow([
            'Configuration',
            ! empty($accounts) ? '✅ Loaded' : '❌ Missing',
            count($accounts) . ' accounts configured',
        ]);

        $table->addRow([
            'Queue System',
            ! empty($queueConfig) ? '✅ Configured' : '⚠️ Default',
            'Connection: ' . ($queueConfig['connection'] ?? 'default'),
        ]);

        $table->addRow([
            'Webhooks',
            ! empty($webhookConfig) ? '✅ Configured' : '⚠️ Default',
            'Enabled: ' . (($webhookConfig['enabled'] ?? false) ? 'Yes' : 'No'),
        ]);

        // Check database tables
        $migrations = ['chatwoot_accounts', 'chatwoot_templates', 'chatwoot_messages', 'chatwoot_webhook_events'];
        $tablesExist = 0;

        foreach ($migrations as $tableName) {
            if (\Schema::hasTable($tableName)) {
                $tablesExist++;
            }
        }

        $table->addRow([
            'Database',
            $tablesExist === count($migrations) ? '✅ Ready' : '❌ Incomplete',
            "$tablesExist/" . count($migrations) . ' tables exist',
        ]);

        $table->render();
        $this->newLine();

        if ($tablesExist < count($migrations)) {
            $this->warn('⚠️ Run migrations with: php artisan migrate');
        }
    }

    protected function showAccounts(): void
    {
        $accounts = config('chatwoot.accounts', []);

        if (empty($accounts)) {
            $this->warn('⚠️ No accounts configured in config/chatwoot.php');

            return;
        }

        $this->info('📱 Configured Accounts:');
        $this->newLine();

        $table = new Table($this->output);
        $table->setHeaders(['Account Key', 'URL', 'Inboxes', 'Status']);

        foreach ($accounts as $key => $config) {
            $url = $config['url'] ?? 'Not configured';
            $inboxCount = count($config['inboxes'] ?? []);

            // Quick connection test
            try {
                $this->accountManager->account($key);
                $hasToken = ! empty($this->accountManager->getAccountToken($key));
                $status = $hasToken ? '✅ Ready' : '❌ No Token';
            } catch (\Exception $e) {
                $status = '❌ Invalid';
            }

            $table->addRow([
                $key,
                strlen($url) > 50 ? substr($url, 0, 47) . '...' : $url,
                $inboxCount,
                $status,
            ]);
        }

        $table->render();
        $this->newLine();

        $this->info('💡 Test connections with: <comment>chatwoot:test-connection</comment>');
    }

    protected function showStatistics(): void
    {
        $this->info('📈 Usage Statistics (Last 30 Days):');
        $this->newLine();

        try {
            $stats = $this->messageService->getMessageStatistics(null, null, 30);

            $table = new Table($this->output);
            $table->setHeaders(['Metric', 'Value']);

            $table->addRows([
                ['Total Messages', number_format($stats['total_messages'])],
                ['Success Rate', number_format($stats['success_rate'], 1) . '%'],
                ['Average Retries', number_format($stats['average_retry_count'], 1)],
            ]);

            if (! empty($stats['by_status'])) {
                $table->addRow(['', '']); // Separator
                foreach ($stats['by_status'] as $status => $count) {
                    $table->addRow([ucfirst($status), number_format($count)]);
                }
            }

            if (! empty($stats['by_channel'])) {
                $table->addRow(['', '']); // Separator
                foreach ($stats['by_channel'] as $channel => $count) {
                    $table->addRow([ucfirst($channel) . ' Channel', number_format($count)]);
                }
            }

            $table->render();

        } catch (\Exception $e) {
            $this->error('❌ Failed to load statistics: ' . $e->getMessage());
            $this->info('💡 Make sure database migrations are run and you have sent some messages.');
        }

        $this->newLine();
    }
}
