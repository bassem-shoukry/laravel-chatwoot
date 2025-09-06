<?php

namespace BassamShoukry\LaravelChatwoot\Commands;

use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use BassamShoukry\LaravelChatwoot\Services\InboxManager;
use BassamShoukry\LaravelChatwoot\Services\TemplateService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class SyncTemplatesCommand extends Command
{
    public $signature = 'chatwoot:sync-templates 
                        {account? : Account key to sync templates for (optional, will sync all if not provided)}
                        {--source=file : Template source: file, database, or api}
                        {--force : Force overwrite existing templates}
                        {--dry-run : Preview changes without applying them}
                        {--validate : Validate templates after sync}
                        {--format=table : Output format: table, json, or count}';
    public $description = 'Sync templates between different sources (files, database, API)';
    protected AccountManager $accountManager;
    protected InboxManager $inboxManager;
    protected TemplateService $templateService;

    public function __construct(
        AccountManager $accountManager,
        InboxManager $inboxManager,
        TemplateService $templateService
    ) {
        parent::__construct();
        $this->accountManager = $accountManager;
        $this->inboxManager = $inboxManager;
        $this->templateService = $templateService;
    }

    public function handle(): int
    {
        $accountKey = $this->argument('account');
        $source = $this->option('source');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $validate = $this->option('validate');
        $format = $this->option('format');

        if (! in_array($source, ['file', 'database', 'api'])) {
            $this->error("Invalid source '$source'. Must be one of: file, database, api");

            return self::FAILURE;
        }

        $this->info('🔄 Starting template synchronization...');
        $this->newLine();

        $accounts = config('chatwoot.accounts', []);
        if (empty($accounts)) {
            $this->error('No Chatwoot accounts configured.');

            return self::FAILURE;
        }

        $accountsToSync = $accountKey ? [$accountKey => $accounts[$accountKey] ?? null] : $accounts;
        $syncResults = [];
        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($accountsToSync as $key => $accountConfig) {
            if (! $accountConfig) {
                $this->error("❌ Account '$key' not found in configuration");

                continue;
            }

            $this->info("📁 Processing account: $key");

            try {
                $this->accountManager->account($key);
                $result = $this->syncAccountTemplates($key, $source, $force, $dryRun, $validate);

                $syncResults[$key] = $result;
                $totalSynced += $result['synced'];
                $totalErrors += $result['errors'];

                if ($result['success']) {
                    $this->info("  ✅ Synced {$result['synced']} templates");
                    if ($result['errors'] > 0) {
                        $this->warn("  ⚠️ {$result['errors']} errors occurred");
                    }
                } else {
                    $this->error('  ❌ Sync failed: ' . $result['error']);
                }

            } catch (\Exception $e) {
                $this->error("  ❌ Account '$key' sync failed: " . $e->getMessage());
                $syncResults[$key] = [
                    'success'   => false,
                    'error'     => $e->getMessage(),
                    'synced'    => 0,
                    'errors'    => 1,
                    'templates' => [],
                ];
                $totalErrors++;
            }

            $this->newLine();
        }

        // Display results based on format
        $this->displayResults($syncResults, $format, $dryRun);

        // Summary
        if ($dryRun) {
            $this->info('🧪 Dry run completed - no changes made');
        } else {
            $this->info('📊 Sync completed:');
            $this->info("   Total templates synced: $totalSynced");
            $this->info("   Total errors: $totalErrors");
        }

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function syncAccountTemplates(string $accountKey, string $source, bool $force, bool $dryRun, bool $validate): array
    {
        $templates = [];
        $synced = 0;
        $errors = 0;
        $errorMessages = [];

        try {
            // Get templates from source
            switch ($source) {
                case 'file':
                    $templates = $this->getFileTemplates($accountKey);

                    break;

                case 'database':
                    $templates = $this->getDatabaseTemplates($accountKey);

                    break;

                case 'api':
                    $templates = $this->getApiTemplates($accountKey);

                    break;
            }

            $this->info('  📋 Found ' . count($templates) . " templates from $source");

            foreach ($templates as $templateKey => $templateData) {
                try {
                    if (! $dryRun) {
                        // Check if template exists
                        $exists = $this->templateService->templateExists($templateKey, $accountKey);

                        if ($exists && ! $force) {
                            $this->info("    ⏭️ Skipping existing template: $templateKey");

                            continue;
                        }

                        // Store or update template
                        $this->templateService->storeTemplate($templateKey, $templateData, $accountKey);

                        // Validate if requested
                        if ($validate) {
                            $validation = $this->templateService->validateTemplate($templateKey, [], $accountKey);
                            if (! $validation['valid']) {
                                $errorMessages[] = "Template '$templateKey' validation failed: " .
                                    implode(', ', $validation['errors']);
                                $errors++;
                            }
                        }
                    }

                    $this->info('    ✅ ' . ($dryRun ? '[DRY RUN] Would sync' : 'Synced') . " template: $templateKey");
                    $synced++;

                } catch (\Exception $e) {
                    $this->error("    ❌ Failed to sync template '$templateKey': " . $e->getMessage());
                    $errorMessages[] = "Template '$templateKey': " . $e->getMessage();
                    $errors++;
                }
            }

        } catch (\Exception $e) {
            return [
                'success'   => false,
                'error'     => $e->getMessage(),
                'synced'    => $synced,
                'errors'    => $errors + 1,
                'templates' => [],
            ];
        }

        return [
            'success'        => true,
            'synced'         => $synced,
            'errors'         => $errors,
            'templates'      => $templates,
            'error_messages' => $errorMessages,
        ];
    }

    protected function getFileTemplates(string $accountKey): array
    {
        $templatePath = config('chatwoot.templates.storage_path', resource_path('chatwoot/templates'));
        $accountPath = $templatePath . '/' . $accountKey;

        if (! is_dir($accountPath)) {
            $this->warn("  ⚠️ Template directory not found: $accountPath");

            return [];
        }

        $templates = [];
        $files = glob($accountPath . '/*.{json,php}', GLOB_BRACE);

        foreach ($files as $file) {
            $templateKey = pathinfo($file, PATHINFO_FILENAME);
            $extension = pathinfo($file, PATHINFO_EXTENSION);

            try {
                if ($extension === 'json') {
                    $templateData = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                } else { // PHP file
                    $templateData = include $file;
                }

                if (is_array($templateData)) {
                    $templates[$templateKey] = $templateData;
                } else {
                    $this->warn("    ⚠️ Invalid template data in file: $file");
                }

            } catch (\Exception $e) {
                $this->error("    ❌ Failed to load template file '$file': " . $e->getMessage());
            }
        }

        return $templates;
    }

    protected function getDatabaseTemplates(string $accountKey): array
    {
        return $this->templateService->getAvailableTemplates($accountKey);
    }

    protected function getApiTemplates(string $accountKey): array
    {
        // This would require API endpoints for fetching templates from Chatwoot
        // For now, we'll return empty array as this isn't a standard Chatwoot API feature
        $this->warn("  ⚠️ API template sync not implemented - Chatwoot doesn't provide template management API");

        return [];
    }

    protected function displayResults(array $results, string $format, bool $dryRun): void
    {
        switch ($format) {
            case 'json':
                $this->line(json_encode($results, JSON_PRETTY_PRINT));

                break;

            case 'count':
                foreach ($results as $account => $result) {
                    $this->info("$account: {$result['synced']} templates, {$result['errors']} errors");
                }

                break;

            case 'table':
            default:
                $this->displayResultTable($results, $dryRun);

                break;
        }
    }

    protected function displayResultTable(array $results, bool $dryRun): void
    {
        $this->info($dryRun ? '🧪 Sync Preview:' : '📊 Sync Results:');
        $this->newLine();

        $table = new Table($this->output);
        $table->setHeaders(['Account', 'Templates', 'Errors', 'Status']);

        foreach ($results as $account => $result) {
            $status = $result['success'] ?
                ($result['errors'] > 0 ? '⚠️ Partial' : '✅ Success') :
                '❌ Failed';

            $table->addRow([
                $account,
                $result['synced'],
                $result['errors'],
                $status,
            ]);
        }

        $table->render();
        $this->newLine();

        // Show errors if any
        foreach ($results as $account => $result) {
            if (! empty($result['error_messages'])) {
                $this->error("Errors for account '$account':");
                foreach ($result['error_messages'] as $error) {
                    $this->error("  • $error");
                }
                $this->newLine();
            }
        }
    }
}
