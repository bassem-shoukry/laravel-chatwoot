<?php

namespace BassamShoukry\LaravelChatwoot\Services\Api;

class InboxesApi extends BaseApiService
{
    /**
     * Get all inboxes for the account.
     */
    public function list(): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get a specific inbox.
     */
    public function get(int $inboxId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes/$inboxId";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Create a new inbox.
     */
    public function create(array $inboxData): array
    {
        $this->validateRequiredFields($inboxData, ['name', 'channel']);

        $allowedFields = [
            'name', 'channel', 'website_url', 'website_name', 'welcome_title',
            'welcome_tagline', 'greeting_enabled', 'greeting_message',
            'working_hours_enabled', 'out_of_office_message', 'timezone',
        ];

        $filteredData = $this->filterAllowedFields($inboxData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes";

        return $this->makeRequest('POST', $endpoint, $filteredData);
    }

    /**
     * Update an existing inbox.
     */
    public function update(int $inboxId, array $inboxData): array
    {
        $allowedFields = [
            'name', 'website_url', 'website_name', 'welcome_title',
            'welcome_tagline', 'greeting_enabled', 'greeting_message',
            'working_hours_enabled', 'out_of_office_message', 'timezone',
            'channel_type', 'csat_survey_enabled',
        ];

        $filteredData = $this->filterAllowedFields($inboxData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes/$inboxId";

        return $this->makeRequest('PATCH', $endpoint, $filteredData);
    }

    /**
     * Delete an inbox.
     */
    public function delete(int $inboxId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes/$inboxId";

        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Get inbox agents.
     */
    public function getAgents(int $inboxId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes/$inboxId/agents";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Add agent to inbox.
     */
    public function addAgent(int $inboxId, int $agentId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes/$inboxId/agents";

        $data = ['user_ids' => [$agentId]];

        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Remove agent from inbox.
     */
    public function removeAgent(int $inboxId, int $agentId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes/$inboxId/agents";

        $data = ['user_ids' => [$agentId]];

        return $this->makeRequest('DELETE', $endpoint, $data);
    }

    /**
     * Update inbox agents.
     */
    public function updateAgents(int $inboxId, array $agentIds): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes/$inboxId/agents";

        $data = ['user_ids' => $agentIds];

        return $this->makeRequest('PATCH', $endpoint, $data);
    }

    /**
     * Get inbox campaigns.
     */
    public function getCampaigns(int $inboxId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes/$inboxId/campaigns";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get inbox by channel type.
     */
    public function getByChannelType(string $channelType): array
    {
        $inboxes = $this->list();
        $filtered = [];

        if (isset($inboxes['payload'])) {
            foreach ($inboxes['payload'] as $inbox) {
                if ($inbox['channel_type'] === $channelType) {
                    $filtered[] = $inbox;
                }
            }
        }

        return $filtered;
    }

    /**
     * Get website inboxes.
     */
    public function getWebsiteInboxes(): array
    {
        return $this->getByChannelType('Channel::WebWidget');
    }

    /**
     * Get WhatsApp inboxes.
     */
    public function getWhatsAppInboxes(): array
    {
        return $this->getByChannelType('Channel::Whatsapp');
    }

    /**
     * Get Facebook inboxes.
     */
    public function getFacebookInboxes(): array
    {
        return $this->getByChannelType('Channel::FacebookPage');
    }

    /**
     * Get email inboxes.
     */
    public function getEmailInboxes(): array
    {
        return $this->getByChannelType('Channel::Email');
    }

    /**
     * Get SMS inboxes.
     */
    public function getSmsInboxes(): array
    {
        return $this->getByChannelType('Channel::Sms');
    }

    /**
     * Get API inboxes.
     */
    public function getApiInboxes(): array
    {
        return $this->getByChannelType('Channel::Api');
    }

    /**
     * Enable/disable inbox features.
     */
    public function updateSettings(int $inboxId, array $settings): array
    {
        $allowedSettings = [
            'greeting_enabled', 'greeting_message',
            'working_hours_enabled', 'out_of_office_message',
            'csat_survey_enabled', 'continuity_via_email',
        ];

        $filteredSettings = $this->filterAllowedFields($settings, $allowedSettings);

        return $this->update($inboxId, $filteredSettings);
    }

    /**
     * Get inbox statistics.
     */
    public function getStatistics(int $inboxId, array $params = []): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/inboxes/$inboxId/conversations/meta";

        return $this->makeRequest('GET', $endpoint, [], $params);
    }

    /**
     * Get inbox working hours.
     */
    public function getWorkingHours(int $inboxId): array
    {
        $inbox = $this->get($inboxId);

        return $inbox['working_hours'] ?? [];
    }

    /**
     * Update inbox working hours.
     */
    public function updateWorkingHours(int $inboxId, array $workingHours): array
    {
        return $this->update($inboxId, [
            'working_hours_enabled' => true,
            'working_hours'         => $workingHours,
        ]);
    }

    /**
     * Set inbox auto-assignment.
     */
    public function setAutoAssignment(int $inboxId, bool $enabled, string $type = 'round_robin'): array
    {
        return $this->update($inboxId, [
            'auto_assignment' => $enabled,
            'assignment_type' => $type,
        ]);
    }

    /**
     * Create website inbox.
     */
    public function createWebsiteInbox(array $websiteData): array
    {
        $this->validateRequiredFields($websiteData, ['name', 'website_url']);

        $inboxData = array_merge($websiteData, [
            'channel' => [
                'type'        => 'web_widget',
                'website_url' => $websiteData['website_url'],
            ],
        ]);

        return $this->create($inboxData);
    }

    /**
     * Create API inbox.
     */
    public function createApiInbox(array $apiData): array
    {
        $this->validateRequiredFields($apiData, ['name']);

        $inboxData = array_merge($apiData, [
            'channel' => [
                'type' => 'api',
            ],
        ]);

        return $this->create($inboxData);
    }

    /**
     * Get inbox webhook URL.
     */
    public function getWebhookUrl(int $inboxId): ?string
    {
        $inbox = $this->get($inboxId);

        if (isset($inbox['webhook_url'])) {
            return $inbox['webhook_url'];
        }

        // For API channels, construct webhook URL
        if ($inbox['channel_type'] === 'Channel::Api') {
            $account = $this->getCurrentAccount();

            return rtrim($account['url'], '/') . "/webhooks/api/v1/inboxes/$inboxId/contacts/{contact_identifier}/conversations";
        }

        return null;
    }

    /**
     * Test inbox connectivity.
     */
    public function testConnection(int $inboxId): array
    {
        try {
            $inbox = $this->get($inboxId);

            return [
                'success'      => true,
                'inbox_id'     => $inboxId,
                'name'         => $inbox['name'],
                'channel_type' => $inbox['channel_type'],
                'status'       => 'connected',
            ];

        } catch (\Exception $e) {
            return [
                'success'  => false,
                'inbox_id' => $inboxId,
                'error'    => $e->getMessage(),
                'status'   => 'connection_failed',
            ];
        }
    }

    /**
     * Get inbox performance metrics.
     */
    public function getPerformanceMetrics(int $inboxId, array $dateRange = []): array
    {
        $stats = $this->getStatistics($inboxId, $dateRange);

        $metrics = [
            'total_conversations'    => 0,
            'open_conversations'     => 0,
            'resolved_conversations' => 0,
            'response_time'          => null,
            'resolution_time'        => null,
        ];

        if (isset($stats['meta'])) {
            $meta = $stats['meta'];
            $metrics = array_merge($metrics, [
                'total_conversations'    => $meta['all_count'] ?? 0,
                'open_conversations'     => $meta['open_count'] ?? 0,
                'resolved_conversations' => $meta['resolved_count'] ?? 0,
            ]);
        }

        return $metrics;
    }
}
