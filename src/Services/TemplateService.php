<?php

namespace BassamShoukry\LaravelChatwoot\Services;

use BassamShoukry\LaravelChatwoot\Exceptions\TemplateNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TemplateService
{
    protected array $config;
    protected string $storageType;
    protected string $cacheStore;
    protected int $cacheTtl;
    protected string $cachePrefix;

    public function __construct()
    {
        $this->config = Config::get('chatwoot', []);
        $this->storageType = $this->config['templates']['storage'] ?? 'database';
        $this->cacheStore = $this->config['cache']['store'] ?? 'default';
        $this->cacheTtl = $this->config['cache']['templates']['ttl'] ?? 1800;
        $this->cachePrefix = $this->config['cache']['templates']['prefix'] ?? 'chatwoot_templates';
    }

    /**
     * Get a template by its key.
     */
    public function getTemplate(string $templateKey, ?string $accountKey = null, ?string $inboxKey = null): array
    {
        $cacheKey = $this->getCacheKey($templateKey, $accountKey, $inboxKey);

        return Cache::store($this->cacheStore)->remember($cacheKey, $this->cacheTtl, function () use ($templateKey, $accountKey, $inboxKey) {
            return $this->loadTemplate($templateKey, $accountKey, $inboxKey);
        });
    }

    /**
     * Get all templates.
     */
    public function getAllTemplates(): Collection
    {
        $cacheKey = "$this->cachePrefix:all_templates";

        return Cache::store($this->cacheStore)->remember($cacheKey, $this->cacheTtl, function () {
            return $this->loadAllTemplates();
        });
    }

    /**
     * Get templates for a specific account.
     */
    public function getTemplatesByAccount(string $accountKey): Collection
    {
        $cacheKey = "$this->cachePrefix:account:$accountKey";

        return Cache::store($this->cacheStore)->remember($cacheKey, $this->cacheTtl, function () use ($accountKey) {
            return $this->loadTemplatesByAccount($accountKey);
        });
    }

    /**
     * Get templates for a specific account and inbox.
     */
    public function getTemplatesByInbox(string $accountKey, string $inboxKey): Collection
    {
        $cacheKey = "$this->cachePrefix:account:$accountKey:inbox:$inboxKey";

        return Cache::store($this->cacheStore)->remember($cacheKey, $this->cacheTtl, function () use ($accountKey, $inboxKey) {
            return $this->loadTemplatesByInbox($accountKey, $inboxKey);
        });
    }

    /**
     * Validate template exists and has required variables.
     */
    public function validateTemplate(string $templateKey, array $variables = [], ?string $accountKey = null, ?string $inboxKey = null): array
    {
        try {
            $template = $this->getTemplate($templateKey, $accountKey, $inboxKey);
            $errors = [];

            // Check required variables
            $requiredVariables = $template['variables'] ?? [];
            $providedVariables = array_keys($variables);

            if ($this->config['templates']['validation']['strict_variables'] ?? true) {
                $missingVariables = array_diff($requiredVariables, $providedVariables);
                if (! empty($missingVariables)) {
                    $errors[] = 'Missing required variables: ' . implode(', ', $missingVariables);
                }
            }

            // Check content size
            $processedContent = $this->processTemplate($templateKey, $variables, $accountKey, $inboxKey);
            $contentSize = strlen($processedContent['content']['text'] ?? '');
            $maxSize = $this->config['templates']['validation']['max_size'] ?? 10000;

            if ($contentSize > $maxSize) {
                $errors[] = "Processed template content exceeds maximum size of $maxSize characters";
            }

            // Check channel restrictions
            $channelRestrictions = $template['channel_restrictions'] ?? [];
            if (! empty($channelRestrictions) && ! is_array($channelRestrictions)) {
                $errors[] = 'Channel restrictions must be an array';
            }

            return [
                'valid'          => empty($errors),
                'errors'         => $errors,
                'template'       => $template,
                'processed_size' => $contentSize,
            ];

        } catch (TemplateNotFoundException $e) {
            return [
                'valid'          => false,
                'errors'         => [$e->getMessage()],
                'template'       => null,
                'processed_size' => 0,
            ];
        }
    }

    /**
     * Process template with variable substitution.
     */
    public function processTemplate(string $templateKey, array $variables = [], ?string $accountKey = null, ?string $inboxKey = null): array
    {
        $template = $this->getTemplate($templateKey, $accountKey, $inboxKey);
        $content = $template['content'];

        // Process variable substitution
        if (isset($content['text'])) {
            $content['text'] = $this->substituteVariables($content['text'], $variables);
        }

        // Process other content fields if they exist
        foreach (['subject', 'title', 'description'] as $field) {
            if (isset($content[$field])) {
                $content[$field] = $this->substituteVariables($content[$field], $variables);
            }
        }

        // Process metadata if it contains template strings
        if (isset($content['metadata']) && is_array($content['metadata'])) {
            $content['metadata'] = $this->processMetadata($content['metadata'], $variables);
        }

        return [
            'template_key'   => $templateKey,
            'account_key'    => $accountKey,
            'inbox_key'      => $inboxKey,
            'content'        => $content,
            'variables_used' => $variables,
            'processed_at'   => now()->toISOString(),
        ];
    }

    /**
     * Store or update a template.
     */
    public function storeTemplate(array $templateData): bool
    {
        try {
            $validated = $this->validateTemplateData($templateData);

            if (! $validated['valid']) {
                return false;
            }

            switch ($this->storageType) {
                case 'database':
                    return $this->storeInDatabase($templateData);

                case 'file':
                    return $this->storeInFile($templateData);

                default:
                    return false;
            }

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a template.
     */
    public function deleteTemplate(string $templateKey, ?string $accountKey = null, ?string $inboxKey = null): bool
    {
        try {
            switch ($this->storageType) {
                case 'database':
                    return $this->deleteFromDatabase($templateKey, $accountKey, $inboxKey);

                case 'file':
                    return $this->deleteFromFile($templateKey, $accountKey, $inboxKey);

                default:
                    return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clear template cache.
     */
    public function clearCache(?string $templateKey = null, ?string $accountKey = null, ?string $inboxKey = null): void
    {
        $cache = Cache::store($this->cacheStore);

        if ($templateKey) {
            // Clear specific template
            $key = $this->getCacheKey($templateKey, $accountKey, $inboxKey);
            $cache->forget($key);
        } else {
            // Clear all template cache
            $cache->flush(); // This might be too aggressive, consider using tags
        }
    }

    /**
     * Import templates from array or file.
     */
    public function importTemplates(array $templates): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($templates as $template) {
            if ($this->storeTemplate($template)) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = 'Failed to import template: ' . ($template['template_key'] ?? 'unknown');
            }
        }

        return $results;
    }

    /**
     * Export templates.
     */
    public function exportTemplates(?string $accountKey = null, ?string $inboxKey = null): array
    {
        if ($accountKey && $inboxKey) {
            return $this->getTemplatesByInbox($accountKey, $inboxKey)->toArray();
        } elseif ($accountKey) {
            return $this->getTemplatesByAccount($accountKey)->toArray();
        } else {
            return $this->getAllTemplates()->toArray();
        }
    }

    /**
     * Get template statistics.
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_templates' => 0,
            'by_account'      => [],
            'by_storage_type' => $this->storageType,
            'cache_stats'     => [
                'store'  => $this->cacheStore,
                'ttl'    => $this->cacheTtl,
                'prefix' => $this->cachePrefix,
            ],
        ];

        try {
            $allTemplates = $this->getAllTemplates();
            $stats['total_templates'] = $allTemplates->count();

            // Group by account
            $stats['by_account'] = $allTemplates->groupBy('account_key')
                ->map(function ($templates, $accountKey) {
                    return [
                        'count'   => $templates->count(),
                        'inboxes' => $templates->groupBy('inbox_key')->keys()->toArray(),
                    ];
                })->toArray();

        } catch (\Exception $e) {
            // Continue with default stats
        }

        return $stats;
    }

    /**
     * Load template from storage.
     */
    protected function loadTemplate(string $templateKey, ?string $accountKey = null, ?string $inboxKey = null): array
    {
        switch ($this->storageType) {
            case 'database':
                return $this->loadFromDatabase($templateKey, $accountKey, $inboxKey);

            case 'file':
                return $this->loadFromFile($templateKey, $accountKey, $inboxKey);

            default:
                throw new TemplateNotFoundException("Template storage type '{$this->storageType}' not supported");
        }
    }

    /**
     * Load from database.
     */
    protected function loadFromDatabase(string $templateKey, ?string $accountKey = null, ?string $inboxKey = null): array
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_templates')) {
            throw new TemplateNotFoundException('Templates table does not exist');
        }

        $query = DB::table('chatwoot_templates')
            ->where('template_key', $templateKey)
            ->where('is_active', true);

        if ($accountKey) {
            $query->where('account_key', $accountKey);
        }

        if ($inboxKey) {
            $query->where('inbox_key', $inboxKey);
        }

        $template = $query->first();

        if (! $template) {
            throw new TemplateNotFoundException("Template '$templateKey' not found");
        }

        return [
            'template_key'         => $template->template_key,
            'account_key'          => $template->account_key,
            'inbox_key'            => $template->inbox_key,
            'name'                 => $template->name,
            'description'          => $template->description,
            'content'              => json_decode($template->content, true),
            'variables'            => json_decode($template->variables, true) ?? [],
            'channel_restrictions' => json_decode($template->channel_restrictions, true) ?? [],
            'metadata'             => json_decode($template->metadata, true) ?? [],
            'is_active'            => $template->is_active,
            'created_at'           => $template->created_at,
            'updated_at'           => $template->updated_at,
        ];
    }

    /**
     * Load from file.
     */
    protected function loadFromFile(string $templateKey, ?string $accountKey = null, ?string $inboxKey = null): array
    {
        $filePath = $this->getTemplateFilePath($templateKey, $accountKey, $inboxKey);

        if (! File::exists($filePath)) {
            throw new TemplateNotFoundException("Template file '$filePath' not found");
        }

        $content = File::get($filePath);
        $template = json_decode($content, true);

        if (! $template) {
            throw new TemplateNotFoundException("Invalid template file format: $filePath");
        }

        return $template;
    }

    /**
     * Load all templates.
     */
    protected function loadAllTemplates(): Collection
    {
        switch ($this->storageType) {
            case 'database':
                return $this->loadAllFromDatabase();

            case 'file':
                return $this->loadAllFromFiles();

            default:
                return collect();
        }
    }

    /**
     * Load all templates from database.
     */
    protected function loadAllFromDatabase(): Collection
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_templates')) {
            return collect();
        }

        return DB::table('chatwoot_templates')
            ->where('is_active', true)
            ->get()
            ->map(function ($template) {
                return [
                    'template_key'         => $template->template_key,
                    'account_key'          => $template->account_key,
                    'inbox_key'            => $template->inbox_key,
                    'name'                 => $template->name,
                    'description'          => $template->description,
                    'content'              => json_decode($template->content, true),
                    'variables'            => json_decode($template->variables, true) ?? [],
                    'channel_restrictions' => json_decode($template->channel_restrictions, true) ?? [],
                    'metadata'             => json_decode($template->metadata, true) ?? [],
                    'is_active'            => $template->is_active,
                    'created_at'           => $template->created_at,
                    'updated_at'           => $template->updated_at,
                ];
            });
    }

    /**
     * Load templates by account from database.
     */
    protected function loadTemplatesByAccount(string $accountKey): Collection
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_templates')) {
            return collect();
        }

        return $this->loadAllFromDatabase()->where('account_key', $accountKey);
    }

    /**
     * Load templates by inbox from database.
     */
    protected function loadTemplatesByInbox(string $accountKey, string $inboxKey): Collection
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_templates')) {
            return collect();
        }

        return $this->loadAllFromDatabase()
            ->where('account_key', $accountKey)
            ->where('inbox_key', $inboxKey);
    }

    /**
     * Substitute variables in content.
     */
    protected function substituteVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{$key}}", (string) $value, $content);
            $content = str_replace("{{{ {$key} }}}", (string) $value, $content); // Alternative syntax
        }

        return $content;
    }

    /**
     * Process metadata with variable substitution.
     */
    protected function processMetadata(array $metadata, array $variables): array
    {
        foreach ($metadata as $key => $value) {
            if (is_string($value)) {
                $metadata[$key] = $this->substituteVariables($value, $variables);
            } elseif (is_array($value)) {
                $metadata[$key] = $this->processMetadata($value, $variables);
            }
        }

        return $metadata;
    }

    /**
     * Get cache key.
     */
    protected function getCacheKey(string $templateKey, ?string $accountKey = null, ?string $inboxKey = null): string
    {
        $parts = [$this->cachePrefix, $templateKey];

        if ($accountKey) {
            $parts[] = $accountKey;
        }

        if ($inboxKey) {
            $parts[] = $inboxKey;
        }

        return implode(':', $parts);
    }

    /**
     * Get template file path.
     */
    protected function getTemplateFilePath(string $templateKey, ?string $accountKey = null, ?string $inboxKey = null): string
    {
        $basePath = $this->config['templates']['file_storage_path'] ?? storage_path('chatwoot/templates');

        if ($accountKey && $inboxKey) {
            return "$basePath/$accountKey/$inboxKey/$templateKey.json";
        } elseif ($accountKey) {
            return "$basePath/$accountKey/$templateKey.json";
        }

        return "$basePath/$templateKey.json";
    }

    /**
     * Validate template data.
     */
    protected function validateTemplateData(array $templateData): array
    {
        $errors = [];

        $required = ['template_key', 'content'];
        foreach ($required as $field) {
            if (! isset($templateData[$field]) || empty($templateData[$field])) {
                $errors[] = "Field '$field' is required";
            }
        }

        if (isset($templateData['content']) && ! is_array($templateData['content'])) {
            $errors[] = 'Content must be an array';
        }

        if (isset($templateData['variables']) && ! is_array($templateData['variables'])) {
            $errors[] = 'Variables must be an array';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Store template in database.
     */
    protected function storeInDatabase(array $templateData): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_templates')) {
            throw new \RuntimeException('chatwoot_templates table does not exist');
        }

        DB::table('chatwoot_templates')->updateOrInsert(
            [
                'template_key' => $templateData['template_key'],
                'account_key'  => $templateData['account_key'] ?? 'default',
                'inbox_key'    => $templateData['inbox_key'] ?? 'default',
            ],
            [
                'name'                 => $templateData['name'] ?? $templateData['template_key'],
                'description'          => $templateData['description'] ?? null,
                'content'              => json_encode($templateData['content']),
                'variables'            => json_encode($templateData['variables'] ?? []),
                'channel_restrictions' => json_encode($templateData['channel_restrictions'] ?? []),
                'metadata'             => json_encode($templateData['metadata'] ?? []),
                'is_active'            => $templateData['is_active'] ?? true,
                'updated_at'           => now(),
            ]
        );

        $this->clearCache($templateData['template_key'], $templateData['account_key'] ?? null, $templateData['inbox_key'] ?? null);

        return true;
    }

    /**
     * Store template in file.
     */
    protected function storeInFile(array $templateData): bool
    {
        $filePath = $this->getTemplateFilePath(
            $templateData['template_key'],
            $templateData['account_key'] ?? null,
            $templateData['inbox_key'] ?? null
        );

        // Ensure directory exists
        File::makeDirectory(dirname($filePath), 0755, true);

        File::put($filePath, json_encode($templateData, JSON_PRETTY_PRINT));

        $this->clearCache($templateData['template_key'], $templateData['account_key'] ?? null, $templateData['inbox_key'] ?? null);

        return true;
    }
}
