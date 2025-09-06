<?php

namespace BassamShoukry\LaravelChatwoot\Services;

use BassamShoukry\LaravelChatwoot\Exceptions\AccountNotFoundException;
use BassamShoukry\LaravelChatwoot\Exceptions\InboxNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class InboxManager
{
    protected AccountManager $accountManager;
    protected ?string $currentInbox = null;

    public function __construct(AccountManager $accountManager)
    {
        $this->accountManager = $accountManager;
    }

    /**
     * Select a specific inbox within the current account.
     */
    public function inbox(string $inboxId): self
    {
        $currentAccount = $this->accountManager->getCurrentAccount();

        if (! $currentAccount) {
            throw new AccountNotFoundException('No account selected. Please select an account first.');
        }

        if (! $this->validateInbox($currentAccount, $inboxId)) {
            throw new InboxNotFoundException("Inbox '$inboxId' not found in account '$currentAccount'");
        }

        $this->currentInbox = $inboxId;

        return $this;
    }

    /**
     * Get all inboxes for the current account.
     */
    public function getInboxes(): Collection
    {
        $currentAccount = $this->accountManager->getCurrentAccount();

        if (! $currentAccount) {
            throw new AccountNotFoundException('No account selected. Please select an account first.');
        }

        return $this->getInboxesForAccount($currentAccount);
    }

    /**
     * Get all inboxes for a specific account.
     */
    public function getInboxesForAccount(string $accountId): Collection
    {
        $accountConfig = $this->accountManager->getAccountConfig($accountId);
        $inboxes = $accountConfig['inboxes'] ?? [];

        return collect($inboxes)->map(function ($config, $key) use ($accountId) {
            return array_merge($config, [
                'inbox_key'   => $key,
                'account_key' => $accountId,
            ]);
        });
    }

    /**
     * Validate if an inbox exists and is accessible.
     */
    public function validateInbox(string $accountId, string $inboxId): bool
    {
        try {
            $accountConfig = $this->accountManager->getAccountConfig($accountId);

            return isset($accountConfig['inboxes'][$inboxId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get configuration for a specific inbox.
     */
    public function getInboxConfig(string $inboxId): array
    {
        $currentAccount = $this->accountManager->getCurrentAccount();

        if (! $currentAccount) {
            throw new AccountNotFoundException('No account selected. Please select an account first.');
        }

        return $this->getInboxConfigForAccount($currentAccount, $inboxId);
    }

    /**
     * Get configuration for a specific inbox in a specific account.
     */
    public function getInboxConfigForAccount(string $accountId, string $inboxId): array
    {
        if (! $this->validateInbox($accountId, $inboxId)) {
            throw new InboxNotFoundException("Inbox '$inboxId' not found in account '$accountId'");
        }

        $accountConfig = $this->accountManager->getAccountConfig($accountId);
        $inboxConfig = $accountConfig['inboxes'][$inboxId];

        return array_merge($inboxConfig, [
            'inbox_key'   => $inboxId,
            'account_key' => $accountId,
        ]);
    }

    /**
     * Get the default inbox for the current account.
     */
    public function getDefaultInbox(): string
    {
        $currentAccount = $this->accountManager->getCurrentAccount();

        if (! $currentAccount) {
            throw new AccountNotFoundException('No account selected. Please select an account first.');
        }

        $accountConfig = $this->accountManager->getAccountConfig($currentAccount);

        return $accountConfig['default_inbox'] ?? $this->getFirstAvailableInbox($currentAccount);
    }

    /**
     * Switch to the default inbox for the current account.
     */
    public function useDefaultInbox(): self
    {
        $defaultInbox = $this->getDefaultInbox();

        return $this->inbox($defaultInbox);
    }

    /**
     * Get the currently selected inbox.
     */
    public function getCurrentInbox(): ?string
    {
        return $this->currentInbox ?? $this->getDefaultInbox();
    }

    /**
     * Get available channels for the current inbox.
     */
    public function getAvailableChannels(): array
    {
        if (! $this->currentInbox) {
            throw new InboxNotFoundException('No inbox selected. Please select an inbox first.');
        }

        $config = $this->getInboxConfig($this->currentInbox);

        return $config['channels'] ?? [];
    }

    /**
     * Check if a channel is available for the current inbox.
     */
    public function hasChannel(string $channel): bool
    {
        $availableChannels = $this->getAvailableChannels();

        return in_array($channel, $availableChannels);
    }

    /**
     * Get available templates for the current inbox.
     */
    public function getAvailableTemplates(): array
    {
        if (! $this->currentInbox) {
            throw new InboxNotFoundException('No inbox selected. Please select an inbox first.');
        }

        $config = $this->getInboxConfig($this->currentInbox);

        return $config['templates'] ?? [];
    }

    /**
     * Check if a template is available for the current inbox.
     */
    public function hasTemplate(string $templateId): bool
    {
        $availableTemplates = $this->getAvailableTemplates();

        return in_array($templateId, $availableTemplates);
    }

    /**
     * Get rate limits for the current inbox.
     */
    public function getRateLimits(): array
    {
        if (! $this->currentInbox) {
            throw new InboxNotFoundException('No inbox selected. Please select an inbox first.');
        }

        $config = $this->getInboxConfig($this->currentInbox);

        return $config['rate_limits'] ?? [
            'per_minute' => 60,
            'per_hour'   => 1000,
            'per_day'    => 20000,
        ];
    }

    /**
     * Get Chatwoot inbox ID for API calls.
     */
    public function getChatwootInboxId(): ?string
    {
        if (! $this->currentInbox) {
            throw new InboxNotFoundException('No inbox selected. Please select an inbox first.');
        }

        $config = $this->getInboxConfig($this->currentInbox);

        return $config['id'] ?? null;
    }

    /**
     * Get inbox name for display purposes.
     */
    public function getInboxName(): string
    {
        if (! $this->currentInbox) {
            throw new InboxNotFoundException('No inbox selected. Please select an inbox first.');
        }

        $config = $this->getInboxConfig($this->currentInbox);

        return $config['name'] ?? $this->currentInbox;
    }

    /**
     * Select the best inbox for a specific channel.
     */
    public function selectBestInboxForChannel(string $channel): self
    {
        $currentAccount = $this->accountManager->getCurrentAccount();

        if (! $currentAccount) {
            throw new AccountNotFoundException('No account selected. Please select an account first.');
        }

        $inboxes = $this->getInboxesForAccount($currentAccount);

        // Find inboxes that support the specified channel
        $compatibleInboxes = $inboxes->filter(function ($inbox) use ($channel) {
            return in_array($channel, $inbox['channels'] ?? []);
        });

        if ($compatibleInboxes->isEmpty()) {
            throw new InboxNotFoundException("No inbox supports channel '$channel' in account '$currentAccount'");
        }

        // Select the first compatible inbox (you could add more logic here for prioritization)
        $selectedInbox = $compatibleInboxes->first();

        Log::info("Auto-selected inbox '{$selectedInbox['inbox_key']}' for channel '$channel'");

        return $this->inbox($selectedInbox['inbox_key']);
    }

    /**
     * Get routing information for message sending.
     */
    public function getRoutingInfo(): array
    {
        $currentAccount = $this->accountManager->getCurrentAccount();
        $currentInbox = $this->getCurrentInbox();

        if (! $currentAccount || ! $currentInbox) {
            throw new \RuntimeException('Account and inbox must be selected for routing');
        }

        $inboxConfig = $this->getInboxConfig($currentInbox);

        return [
            'account_key'   => $currentAccount,
            'account_url'   => $this->accountManager->getAccountUrl($currentAccount),
            'account_token' => $this->accountManager->getAccountToken($currentAccount),
            'inbox_key'     => $currentInbox,
            'inbox_id'      => $this->getChatwootInboxId(),
            'inbox_name'    => $this->getInboxName(),
            'channels'      => $this->getAvailableChannels(),
            'templates'     => $this->getAvailableTemplates(),
            'rate_limits'   => $this->getRateLimits(),
        ];
    }

    /**
     * Reset to default state.
     */
    public function reset(): self
    {
        $this->currentInbox = null;

        return $this;
    }

    /**
     * Get the first available inbox for an account.
     */
    protected function getFirstAvailableInbox(string $accountId): string
    {
        $inboxes = $this->getInboxesForAccount($accountId);

        if ($inboxes->isEmpty()) {
            throw new InboxNotFoundException("No inboxes found for account '$accountId'");
        }

        return $inboxes->keys()->first();
    }

    /**
     * Check if current inbox configuration is valid.
     */
    public function validateCurrentConfiguration(): bool
    {
        try {
            $currentAccount = $this->accountManager->getCurrentAccount();
            $currentInbox = $this->getCurrentInbox();

            if (! $currentAccount || ! $currentInbox) {
                return false;
            }

            return $this->validateInbox($currentAccount, $currentInbox);
        } catch (\Exception $e) {
            Log::warning('Inbox configuration validation failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get inbox statistics and health information.
     */
    public function getInboxStats(): array
    {
        if (! $this->currentInbox) {
            throw new InboxNotFoundException('No inbox selected. Please select an inbox first.');
        }

        $routingInfo = $this->getRoutingInfo();

        return [
            'account_key'     => $routingInfo['account_key'],
            'inbox_key'       => $routingInfo['inbox_key'],
            'inbox_name'      => $routingInfo['inbox_name'],
            'channels_count'  => count($routingInfo['channels']),
            'templates_count' => count($routingInfo['templates']),
            'rate_limits'     => $routingInfo['rate_limits'],
            'is_configured'   => ! empty($routingInfo['inbox_id']),
            'has_token'       => ! empty($routingInfo['account_token']),
        ];
    }
}
