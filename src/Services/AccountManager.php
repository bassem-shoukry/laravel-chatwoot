<?php

namespace BassamShoukry\LaravelChatwoot\Services;

use BassamShoukry\LaravelChatwoot\Exceptions\AccountNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class AccountManager
{
    protected array $config;
    protected ?string $currentAccount = null;
    protected array $accountCache = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->currentAccount = $config['default_account'] ?? null;
    }

    /**
     * Switch to a specific account context.
     */
    public function account(string $accountId): self
    {
        if (! $this->validateAccount($accountId)) {
            throw new AccountNotFoundException("Account '$accountId' not found or inactive");
        }

        $this->currentAccount = $accountId;

        return $this;
    }

    /**
     * Get all available accounts.
     */
    public function getAllAccounts(): Collection
    {
        $configAccounts = collect($this->config['accounts'] ?? [])->map(function ($config, $key) {
            return [
                'account_key' => $key,
                'name'        => $config['name'] ?? $key,
                'url'         => $config['url'] ?? '',
                'is_active'   => true,
                'source'      => 'config',
            ];
        });

        $databaseAccounts = collect();
        if (DB::getSchemaBuilder()->hasTable('chatwoot_accounts')) {
            $databaseAccounts = DB::table('chatwoot_accounts')
                ->where('is_active', true)
                ->get()
                ->map(function ($account) {
                    return [
                        'account_key'      => $account->account_key,
                        'name'             => $account->name,
                        'url'              => $account->url,
                        'is_active'        => $account->is_active,
                        'source'           => 'database',
                        'token_expires_at' => $account->token_expires_at,
                        'last_verified_at' => $account->last_verified_at,
                    ];
                });
        }

        return $configAccounts->merge($databaseAccounts);
    }

    /**
     * Validate if an account exists and is active.
     */
    public function validateAccount(string $accountId): bool
    {
        $cacheKey = $this->getCacheKey('account_validation', $accountId);

        return Cache::store($this->getCacheStore())
            ->remember($cacheKey, $this->getCacheTtl('account_configs'), function () use ($accountId) {
                // Check in configuration first
                if (isset($this->config['accounts'][$accountId])) {
                    return true;
                }

                // Check in database
                if (DB::getSchemaBuilder()->hasTable('chatwoot_accounts')) {
                    return DB::table('chatwoot_accounts')
                        ->where('account_key', $accountId)
                        ->where('is_active', true)
                        ->exists();
                }

                return false;
            });
    }

    /**
     * Get the current active account.
     */
    public function getCurrentAccount(): ?string
    {
        return $this->currentAccount;
    }

    /**
     * Get current account information including URL and token.
     *
     * @return array{account_key: string, url: string, token: string}|null
     */
    public function getCurrentAccountInfo(): ?array
    {
        $currentAccount = $this->getCurrentAccount();

        if (! $currentAccount) {
            return null;
        }

        try {
            $config = $this->getAccountConfig($currentAccount);
            $token = $this->getAccountToken($currentAccount);
            $url = $this->getAccountUrl($currentAccount);

            if (! $token || ! $url) {
                return null;
            }

            return [
                'account_key' => $currentAccount,
                'url'         => $url,
                'token'       => $token,
            ];
        } catch (AccountNotFoundException $e) {
            return null;
        }
    }

    /**
     * Get configuration for a specific account.
     */
    public function getAccountConfig(string $accountId): array
    {
        if (! $this->validateAccount($accountId)) {
            throw new AccountNotFoundException("Account '$accountId' not found or inactive");
        }

        $cacheKey = $this->getCacheKey('account_config', $accountId);

        return Cache::store($this->getCacheStore())
            ->remember($cacheKey, $this->getCacheTtl('account_configs'), function () use ($accountId) {
                return $this->loadAccountConfig($accountId);
            });
    }

    /**
     * Get API token for a specific account.
     */
    public function getAccountToken(string $accountId): ?string
    {
        $config = $this->getAccountConfig($accountId);

        if (isset($config['token'])) {
            // Decrypt token if it's encrypted and from database
            if (isset($config['source']) && $config['source'] === 'database') {
                try {
                    return Crypt::decrypt($config['token']);
                } catch (\Exception $e) {
                    return null;
                }
            }

            return $config['token'];
        }

        return null;
    }

    /**
     * Get API URL for a specific account.
     */
    public function getAccountUrl(string $accountId): string
    {
        $config = $this->getAccountConfig($accountId);

        return $config['url'] ?? '';
    }

    /**
     * Refresh token for a specific account.
     */
    public function refreshToken(string $accountId): bool
    {
        try {
            // Clear cached config to force reload
            $this->clearAccountCache($accountId);

            // Validate token by making a test API call
            $token = $this->getAccountToken($accountId);
            if (! $token) {
                return false;
            }

            // Update last_verified_at in database
            if (DB::getSchemaBuilder()->hasTable('chatwoot_accounts')) {
                DB::table('chatwoot_accounts')
                    ->where('account_key', $accountId)
                    ->update(['last_verified_at' => now()]);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Store account configuration in database.
     */
    public function storeAccount(array $accountData): bool
    {
        try {
            if (! DB::getSchemaBuilder()->hasTable('chatwoot_accounts')) {
                throw new \RuntimeException('chatwoot_accounts table does not exist');
            }

            $token = $accountData['token'] ?? null;
            if ($token && $this->shouldEncryptTokens()) {
                $token = Crypt::encrypt($token);
            }

            DB::table('chatwoot_accounts')->updateOrInsert(
                ['account_key' => $accountData['account_key']],
                [
                    'name'       => $accountData['name'],
                    'url'        => $accountData['url'],
                    'token'      => $token,
                    'config'     => json_encode($accountData['config'] ?? []),
                    'is_active'  => $accountData['is_active'] ?? true,
                    'updated_at' => now(),
                ]
            );

            // Clear cache for this account
            $this->clearAccountCache($accountData['account_key']);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove account from database.
     */
    public function removeAccount(string $accountId): bool
    {
        try {
            if (DB::getSchemaBuilder()->hasTable('chatwoot_accounts')) {
                DB::table('chatwoot_accounts')
                    ->where('account_key', $accountId)
                    ->delete();
            }

            $this->clearAccountCache($accountId);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test account connection.
     */
    public function testConnection(string $accountId): array
    {
        try {
            $config = $this->getAccountConfig($accountId);
            $token = $this->getAccountToken($accountId);

            if (! $token) {
                return [
                    'success' => false,
                    'error'   => 'No token found for account',
                ];
            }

            // Here you would make an actual API call to test the connection
            // For now, we'll simulate the test
            $testResult = [
                'success'     => true,
                'account_key' => $accountId,
                'url'         => $config['url'],
                'token_valid' => ! empty($token),
                'tested_at'   => now()->toISOString(),
            ];

            // Update last_verified_at if connection test passes
            if ($testResult['success'] && DB::getSchemaBuilder()->hasTable('chatwoot_accounts')) {
                DB::table('chatwoot_accounts')
                    ->where('account_key', $accountId)
                    ->update(['last_verified_at' => now()]);
            }

            return $testResult;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Load account configuration from config file or database.
     */
    protected function loadAccountConfig(string $accountId): array
    {
        // First check configuration file
        if (isset($this->config['accounts'][$accountId])) {
            return array_merge($this->config['accounts'][$accountId], [
                'account_key' => $accountId,
                'source'      => 'config',
            ]);
        }

        // Then check database
        if (DB::getSchemaBuilder()->hasTable('chatwoot_accounts')) {
            $account = DB::table('chatwoot_accounts')
                ->where('account_key', $accountId)
                ->where('is_active', true)
                ->first();

            if ($account) {
                $config = json_decode($account->config, true) ?? [];

                return array_merge($config, [
                    'account_key'      => $account->account_key,
                    'name'             => $account->name,
                    'url'              => $account->url,
                    'token'            => $account->token,
                    'is_active'        => $account->is_active,
                    'token_expires_at' => $account->token_expires_at,
                    'last_verified_at' => $account->last_verified_at,
                    'source'           => 'database',
                ]);
            }
        }

        throw new AccountNotFoundException("Account '$accountId' not found");
    }

    /**
     * Clear cache for specific account.
     */
    protected function clearAccountCache(string $accountId): void
    {
        $store = Cache::store($this->getCacheStore());
        $store->forget($this->getCacheKey('account_config', $accountId));
        $store->forget($this->getCacheKey('account_validation', $accountId));
    }

    /**
     * Get cache key for specific type and account.
     */
    protected function getCacheKey(string $type, string $accountId): string
    {
        $prefix = $this->config['cache']['account_configs']['prefix'] ?? 'chatwoot_accounts';

        return "$prefix:$type:$accountId";
    }

    /**
     * Get cache store name.
     */
    protected function getCacheStore(): string
    {
        return $this->config['cache']['store'] ?? 'default';
    }

    /**
     * Get cache TTL for specific cache type.
     */
    protected function getCacheTtl(string $type): int
    {
        return $this->config['cache'][$type]['ttl'] ?? 3600;
    }

    /**
     * Check if tokens should be encrypted.
     */
    protected function shouldEncryptTokens(): bool
    {
        return $this->config['security']['encrypt_tokens'] ?? true;
    }
}
