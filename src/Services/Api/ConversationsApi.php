<?php

namespace BassamShoukry\LaravelChatwoot\Services\Api;

class ConversationsApi extends BaseApiService
{
    /**
     * Get all conversations for the account.
     */
    public function list(array $params = []): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations";

        return $this->makeRequest('GET', $endpoint, [], $params);
    }

    /**
     * Get paginated conversations with filtering.
     */
    public function getPaginated(int $page = 1, int $perPage = 25, array $filters = []): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations";

        return $this->paginate($endpoint, $page, $perPage, $filters);
    }

    /**
     * Get all conversations (handles pagination automatically).
     */
    public function getAll(array $filters = []): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations";

        return $this->fetchAll($endpoint, $filters);
    }

    /**
     * Get a specific conversation.
     */
    public function get(int $conversationId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Create a new conversation.
     */
    public function create(array $conversationData): array
    {
        $this->validateRequiredFields($conversationData, ['source_id', 'inbox_id']);

        $allowedFields = [
            'source_id', 'inbox_id', 'contact_id', 'additional_attributes',
            'custom_attributes', 'status', 'assignee_id', 'team_id',
        ];

        $filteredData = $this->filterAllowedFields($conversationData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations";

        return $this->makeRequest('POST', $endpoint, $filteredData);
    }

    /**
     * Update conversation status.
     */
    public function updateStatus(int $conversationId, string $status): array
    {
        $validStatuses = ['open', 'resolved', 'pending'];

        if (! in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
        }

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/toggle_status";

        $data = ['status' => $status];

        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Assign conversation to agent.
     */
    public function assign(int $conversationId, int $assigneeId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/assignments";

        $data = ['assignee_id' => $assigneeId];

        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Assign conversation to team.
     */
    public function assignToTeam(int $conversationId, int $teamId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/assignments";

        $data = ['team_id' => $teamId];

        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Add labels to conversation.
     */
    public function addLabels(int $conversationId, array $labels): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/labels";

        $data = ['labels' => $labels];

        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Remove labels from conversation.
     */
    public function removeLabels(int $conversationId, array $labels): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/labels";

        $data = ['labels' => $labels];

        return $this->makeRequest('DELETE', $endpoint, $data);
    }

    /**
     * Get conversation messages.
     */
    public function getMessages(int $conversationId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/messages";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Send a message to conversation.
     */
    public function sendMessage(int $conversationId, array $messageData): array
    {
        $this->validateRequiredFields($messageData, ['content', 'message_type']);

        $allowedFields = [
            'content', 'message_type', 'private', 'content_type',
            'content_attributes', 'attachments', 'template_params',
        ];

        $filteredData = $this->filterAllowedFields($messageData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/messages";

        return $this->makeRequest('POST', $endpoint, $filteredData);
    }

    /**
     * Update conversation custom attributes.
     */
    public function updateCustomAttributes(int $conversationId, array $customAttributes): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/custom_attributes";

        $data = ['custom_attributes' => $customAttributes];

        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Get conversation participants.
     */
    public function getParticipants(int $conversationId): array
    {
        $conversation = $this->get($conversationId);

        return [
            'contact'  => $conversation['meta']['contact'] ?? null,
            'assignee' => $conversation['meta']['assignee'] ?? null,
            'team'     => $conversation['meta']['team'] ?? null,
        ];
    }

    /**
     * Search conversations.
     */
    public function search(string $query, array $filters = []): array
    {
        $params = array_merge($filters, ['q' => $query]);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/search";

        return $this->makeRequest('GET', $endpoint, [], $params);
    }

    /**
     * Get conversations by status.
     */
    public function getByStatus(string $status, array $params = []): array
    {
        $params['status'] = $status;

        return $this->list($params);
    }

    /**
     * Get conversations by assignee.
     */
    public function getByAssignee(int $assigneeId, array $params = []): array
    {
        $params['assignee_type'] = 'User';
        $params['assignee_id'] = $assigneeId;

        return $this->list($params);
    }

    /**
     * Get conversations by inbox.
     */
    public function getByInbox(int $inboxId, array $params = []): array
    {
        $params['inbox_id'] = $inboxId;

        return $this->list($params);
    }

    /**
     * Get conversations by contact.
     */
    public function getByContact(int $contactId, array $params = []): array
    {
        $params['contact_id'] = $contactId;

        return $this->list($params);
    }

    /**
     * Get conversation metrics.
     */
    public function getMetrics(array $params = []): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/meta";

        return $this->makeRequest('GET', $endpoint, [], $params);
    }

    /**
     * Snooze conversation until specific time.
     */
    public function snooze(int $conversationId, string $until): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/snooze";

        $data = ['snoozed_until' => $until];

        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Unsnooze conversation.
     */
    public function unsnooze(int $conversationId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/snooze";

        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Get conversation timeline events.
     */
    public function getTimeline(int $conversationId): array
    {
        $messages = $this->getMessages($conversationId);

        // Process and sort timeline events
        $timeline = [];

        if (isset($messages['payload'])) {
            foreach ($messages['payload'] as $message) {
                $timeline[] = [
                    'type'      => 'message',
                    'timestamp' => $message['created_at'],
                    'data'      => $message,
                ];
            }
        }

        // Sort by timestamp
        usort($timeline, fn ($a, $b) => strtotime($a['timestamp']) <=> strtotime($b['timestamp']));

        return $timeline;
    }

    /**
     * Bulk update conversations.
     */
    public function bulkUpdate(array $conversationIds, array $updateData): array
    {
        $results = [];

        foreach ($conversationIds as $conversationId) {
            try {
                $result = null;

                // Handle different types of updates
                if (isset($updateData['status'])) {
                    $result = $this->updateStatus($conversationId, $updateData['status']);
                }

                if (isset($updateData['assignee_id'])) {
                    $result = $this->assign($conversationId, $updateData['assignee_id']);
                }

                if (isset($updateData['team_id'])) {
                    $result = $this->assignToTeam($conversationId, $updateData['team_id']);
                }

                if (isset($updateData['labels'])) {
                    $result = $this->addLabels($conversationId, $updateData['labels']);
                }

                $results[] = [
                    'success'         => true,
                    'conversation_id' => $conversationId,
                    'data'            => $result,
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'success'         => false,
                    'conversation_id' => $conversationId,
                    'error'           => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
