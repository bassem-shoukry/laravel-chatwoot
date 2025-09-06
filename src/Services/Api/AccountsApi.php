<?php

namespace BassamShoukry\LaravelChatwoot\Services\Api;

use BassamShoukry\LaravelChatwoot\Exceptions\ChatwootApiException;

class AccountsApi extends BaseApiService
{
    /**
     * Get account details.
     *
     * @throws ChatwootApiException
     */
    public function get(): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Update account details.
     *
     * @throws ChatwootApiException
     */
    public function update(array $accountData): array
    {
        $allowedFields = [
            'name', 'locale', 'domain', 'support_email',
            'auto_resolve_duration', 'custom_attributes',
        ];

        $filteredData = $this->filterAllowedFields($accountData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}";

        return $this->makeRequest('PATCH', $endpoint, $filteredData);
    }

    /**
     * Get account users/agents.
     */
    public function getUsers(): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/agents";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get a specific user/agent.
     */
    public function getUser(int $userId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/agents/$userId";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Create a new user/agent.
     */
    public function createUser(array $userData): array
    {
        $this->validateRequiredFields($userData, ['name', 'email', 'role']);

        $allowedFields = [
            'name', 'email', 'role', 'auto_offline', 'availability_status',
        ];

        $filteredData = $this->filterAllowedFields($userData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/agents";

        return $this->makeRequest('POST', $endpoint, $filteredData);
    }

    /**
     * Update user/agent.
     */
    public function updateUser(int $userId, array $userData): array
    {
        $allowedFields = [
            'name', 'email', 'role', 'auto_offline', 'availability_status',
        ];

        $filteredData = $this->filterAllowedFields($userData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/agents/$userId";

        return $this->makeRequest('PATCH', $endpoint, $filteredData);
    }

    /**
     * Delete user/agent.
     */
    public function deleteUser(int $userId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/agents/$userId";

        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Get account teams.
     */
    public function getTeams(): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/teams";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get a specific team.
     */
    public function getTeam(int $teamId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/teams/$teamId";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Create a new team.
     */
    public function createTeam(array $teamData): array
    {
        $this->validateRequiredFields($teamData, ['name']);

        $allowedFields = ['name', 'description', 'allow_auto_assign'];
        $filteredData = $this->filterAllowedFields($teamData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/teams";

        return $this->makeRequest('POST', $endpoint, $filteredData);
    }

    /**
     * Update team.
     */
    public function updateTeam(int $teamId, array $teamData): array
    {
        $allowedFields = ['name', 'description', 'allow_auto_assign'];
        $filteredData = $this->filterAllowedFields($teamData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/teams/$teamId";

        return $this->makeRequest('PATCH', $endpoint, $filteredData);
    }

    /**
     * Delete team.
     */
    public function deleteTeam(int $teamId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/teams/$teamId";

        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Add agents to team.
     */
    public function addAgentsToTeam(int $teamId, array $agentIds): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/teams/$teamId/agents";

        $data = ['user_ids' => $agentIds];

        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Remove agents from team.
     */
    public function removeAgentsFromTeam(int $teamId, array $agentIds): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/teams/$teamId/agents";

        $data = ['user_ids' => $agentIds];

        return $this->makeRequest('DELETE', $endpoint, $data);
    }

    /**
     * Get account labels.
     */
    public function getLabels(): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/labels";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Create a new label.
     */
    public function createLabel(array $labelData): array
    {
        $this->validateRequiredFields($labelData, ['title']);

        $allowedFields = ['title', 'description', 'color', 'show_on_sidebar'];
        $filteredData = $this->filterAllowedFields($labelData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/labels";

        return $this->makeRequest('POST', $endpoint, $filteredData);
    }

    /**
     * Update label.
     */
    public function updateLabel(int $labelId, array $labelData): array
    {
        $allowedFields = ['title', 'description', 'color', 'show_on_sidebar'];
        $filteredData = $this->filterAllowedFields($labelData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/labels/$labelId";

        return $this->makeRequest('PATCH', $endpoint, $filteredData);
    }

    /**
     * Delete label.
     */
    public function deleteLabel(int $labelId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/labels/$labelId";

        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Get account webhooks.
     */
    public function getWebhooks(): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/webhooks";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Create webhook.
     */
    public function createWebhook(array $webhookData): array
    {
        $this->validateRequiredFields($webhookData, ['url']);

        $allowedFields = ['url', 'subscriptions'];
        $filteredData = $this->filterAllowedFields($webhookData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/webhooks";

        return $this->makeRequest('POST', $endpoint, $filteredData);
    }

    /**
     * Update webhook.
     */
    public function updateWebhook(int $webhookId, array $webhookData): array
    {
        $allowedFields = ['url', 'subscriptions'];
        $filteredData = $this->filterAllowedFields($webhookData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/webhooks/$webhookId";

        return $this->makeRequest('PATCH', $endpoint, $filteredData);
    }

    /**
     * Delete webhook.
     */
    public function deleteWebhook(int $webhookId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/webhooks/$webhookId";

        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Get account reports.
     */
    public function getReports(string $type = 'conversations', array $params = []): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/reports/$type";

        return $this->makeRequest('GET', $endpoint, [], $params);
    }

    /**
     * Get conversation reports.
     */
    public function getConversationReports(array $params = []): array
    {
        return $this->getReports('conversations', $params);
    }

    /**
     * Get agent reports.
     */
    public function getAgentReports(array $params = []): array
    {
        return $this->getReports('agents', $params);
    }

    /**
     * Get inbox reports.
     */
    public function getInboxReports(array $params = []): array
    {
        return $this->getReports('inboxes', $params);
    }

    /**
     * Get account summary.
     */
    public function getSummary(): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/reports/summary";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Get account settings.
     *
     * @throws ChatwootApiException
     */
    public function getSettings(): array
    {
        $account = $this->get();

        return [
            'name'                  => $account['name'] ?? null,
            'locale'                => $account['locale'] ?? 'en',
            'domain'                => $account['domain'] ?? null,
            'support_email'         => $account['support_email'] ?? null,
            'auto_resolve_duration' => $account['auto_resolve_duration'] ?? null,
            'features'              => $account['features'] ?? [],
            'limits'                => $account['limits'] ?? [],
        ];
    }

    /**
     * Update account settings.
     *
     * @throws ChatwootApiException
     */
    public function updateSettings(array $settings): array
    {
        return $this->update($settings);
    }

    /**
     * Get account usage statistics.
     */
    public function getUsageStats(): array
    {
        $summary = $this->getSummary();

        return [
            'conversations' => [
                'total'      => $summary['previous_month']['conversations_count'] ?? 0,
                'this_month' => $summary['current_month']['conversations_count'] ?? 0,
            ],
            'messages' => [
                'total'      => $summary['previous_month']['incoming_messages_count'] ?? 0,
                'this_month' => $summary['current_month']['incoming_messages_count'] ?? 0,
            ],
            'agents'  => count($this->getUsers()),
            'inboxes' => count(app(InboxesApi::class)->list()['payload'] ?? []),
        ];
    }

    /**
     * Get account limits and features.
     *
     * @throws ChatwootApiException
     */
    public function getAccountLimits(): array
    {
        $account = $this->get();

        return [
            'limits'   => $account['limits'] ?? [],
            'features' => $account['features'] ?? [],
            'plan'     => $account['plan'] ?? null,
        ];
    }

    /**
     * Test account connection.
     */
    public function testConnection(): array
    {
        try {
            $account = $this->get();

            return [
                'success'      => true,
                'account_id'   => $account['id'],
                'account_name' => $account['name'],
                'status'       => 'connected',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'status'  => 'connection_failed',
            ];
        }
    }
}
