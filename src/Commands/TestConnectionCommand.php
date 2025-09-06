<?php

namespace BassamShoukry\LaravelChatwoot\Commands;

use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use BassamShoukry\LaravelChatwoot\Services\InboxManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class TestConnectionCommand extends Command
{
    public $signature = 'chatwoot:test-connection 
                        {account? : Account key to test (optional, will test all if not provided)}
                        {--timeout=30 : Connection timeout in seconds}
                        {--verbose : Show detailed connection information}';
    public $description = 'Test connections to configured Chatwoot accounts and inboxes';
    protected AccountManager $accountManager;
    protected InboxManager $inboxManager;

    public function __construct(AccountManager $accountManager, InboxManager $inboxManager)
    {
        parent::__construct();
        $this->accountManager = $accountManager;
        $this->inboxManager = $inboxManager;
    }

    public function handle(): int
    {
        $accountKey = $this->argument('account');
        $accounts = config('chatwoot.accounts', []);

        if (empty($accounts)) {
            $this->error('No Chatwoot accounts configured. Please check your config/chatwoot.php file.');

            return self::FAILURE;
        }

        $this->info('🔍 Testing Chatwoot API connections...');
        $this->newLine();

        $results = [];
        $overallSuccess = true;

        // Test specific account or all accounts
        $accountsToTest = $accountKey ? [$accountKey => $accounts[$accountKey] ?? null] : $accounts;

        foreach ($accountsToTest as $key => $accountConfig) {
            if (! $accountConfig) {
                $this->error("❌ Account '$key' not found in configuration");
                $overallSuccess = false;

                continue;
            }

            $this->info("Testing account: $key");

            try {
                // Test account connection
                $this->accountManager->account($key);
                $connectionResult = $this->accountManager->testConnection();

                $accountResult = [
                    'account'       => $key,
                    'url'           => $accountConfig['url'] ?? 'Not configured',
                    'connection'    => $connectionResult['success'] ? '✅ Success' : '❌ Failed',
                    'response_time' => isset($connectionResult['response_time']) ?
                        round($connectionResult['response_time'] * 1000) . 'ms' : 'N/A',
                    'error'   => $connectionResult['error'] ?? null,
                    'inboxes' => [],
                ];

                if ($connectionResult['success']) {
                    $this->info('  ✅ Account connection successful');

                    if ($this->option('verbose')) {
                        $this->info('     URL: ' . ($accountConfig['url'] ?? 'N/A'));
                        $this->info('     Response time: ' . $accountResult['response_time']);
                    }

                    // Test inbox connections if account connection successful
                    $inboxes = $accountConfig['inboxes'] ?? [];
                    if (! empty($inboxes)) {
                        $this->info('  📥 Testing inboxes...');

                        foreach ($inboxes as $inboxKey => $inboxConfig) {
                            try {
                                $this->inboxManager->inbox($inboxKey);
                                $inboxResult = $this->testInboxConnection($inboxKey, $inboxConfig);

                                $accountResult['inboxes'][$inboxKey] = $inboxResult;

                                if ($inboxResult['success']) {
                                    $this->info("    ✅ Inbox '$inboxKey' connection successful");

                                    if ($this->option('verbose')) {
                                        $this->info('       Channels: ' . implode(', ', $inboxConfig['channels'] ?? []));
                                    }
                                } else {
                                    $this->error("    ❌ Inbox '$inboxKey' connection failed: " . $inboxResult['error']);
                                    $overallSuccess = false;
                                }

                            } catch (\Exception $e) {
                                $inboxResult = [
                                    'success' => false,
                                    'error'   => $e->getMessage(),
                                ];
                                $accountResult['inboxes'][$inboxKey] = $inboxResult;
                                $this->error("    ❌ Inbox '$inboxKey' test failed: " . $e->getMessage());
                                $overallSuccess = false;
                            }
                        }
                    }
                } else {
                    $this->error('  ❌ Account connection failed: ' . ($connectionResult['error'] ?? 'Unknown error'));
                    $overallSuccess = false;
                }

                $results[$key] = $accountResult;

            } catch (\Exception $e) {
                $this->error("  ❌ Account '$key' test failed: " . $e->getMessage());
                $results[$key] = [
                    'account'    => $key,
                    'connection' => '❌ Failed',
                    'error'      => $e->getMessage(),
                    'inboxes'    => [],
                ];
                $overallSuccess = false;
            }

            $this->newLine();
        }

        // Display summary table
        $this->displaySummaryTable($results);

        if ($overallSuccess) {
            $this->info('🎉 All connections tested successfully!');

            return self::SUCCESS;
        } else {
            $this->error('❌ Some connections failed. Please check your configuration.');

            return self::FAILURE;
        }
    }

    protected function testInboxConnection(string $inboxKey, array $inboxConfig): array
    {
        try {
            // Get routing information to validate inbox setup
            $routingInfo = $this->inboxManager->getRoutingInfo();

            if (empty($routingInfo['inbox_id'])) {
                return [
                    'success' => false,
                    'error'   => 'Invalid inbox configuration - missing inbox_id',
                ];
            }

            // Validate required channels
            $channels = $inboxConfig['channels'] ?? [];
            if (empty($channels)) {
                return [
                    'success' => false,
                    'error'   => 'No channels configured for inbox',
                ];
            }

            return [
                'success'  => true,
                'inbox_id' => $routingInfo['inbox_id'],
                'channels' => $channels,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    protected function displaySummaryTable(array $results): void
    {
        $this->info('📊 Connection Test Summary:');
        $this->newLine();

        $table = new Table($this->output);
        $table->setHeaders(['Account', 'URL', 'Connection', 'Response Time', 'Inboxes', 'Status']);

        foreach ($results as $result) {
            $inboxCount = count($result['inboxes']);
            $successfulInboxes = count(array_filter($result['inboxes'], fn ($inbox) => $inbox['success'] ?? false));

            $inboxStatus = $inboxCount > 0 ? "$successfulInboxes/$inboxCount" : 'N/A';
            $overallStatus = ($result['connection'] === '✅ Success') &&
                           ($successfulInboxes === $inboxCount || $inboxCount === 0) ? '✅ Pass' : '❌ Fail';

            $table->addRow([
                $result['account'],
                substr($result['url'] ?? '', 0, 30) . (strlen($result['url'] ?? '') > 30 ? '...' : ''),
                $result['connection'],
                $result['response_time'] ?? 'N/A',
                $inboxStatus,
                $overallStatus,
            ]);
        }

        $table->render();
        $this->newLine();
    }
}
