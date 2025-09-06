<?php

namespace BassamShoukry\LaravelChatwoot\Services\Api;

class MessagesApi extends BaseApiService
{
    /**
     * Get messages for a conversation.
     */
    public function list(int $conversationId, array $params = []): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/messages";

        return $this->makeRequest('GET', $endpoint, [], $params);
    }

    /**
     * Send a message to a conversation.
     */
    public function send(int $conversationId, array $messageData): array
    {
        $this->validateRequiredFields($messageData, ['content', 'message_type']);

        $allowedFields = [
            'content', 'message_type', 'private', 'content_type',
            'content_attributes', 'attachments', 'template_params', 'echo_id',
        ];

        $filteredData = $this->filterAllowedFields($messageData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/messages";

        return $this->makeRequest('POST', $endpoint, $filteredData);
    }

    /**
     * Send a text message.
     */
    public function sendText(int $conversationId, string $content, bool $private = false): array
    {
        return $this->send($conversationId, [
            'content'      => $content,
            'message_type' => 'outgoing',
            'private'      => $private,
        ]);
    }

    /**
     * Send a private note.
     */
    public function sendPrivateNote(int $conversationId, string $content): array
    {
        return $this->send($conversationId, [
            'content'      => $content,
            'message_type' => 'outgoing',
            'private'      => true,
        ]);
    }

    /**
     * Send a message with attachments.
     */
    public function sendWithAttachments(int $conversationId, string $content, array $attachments): array
    {
        return $this->send($conversationId, [
            'content'      => $content,
            'message_type' => 'outgoing',
            'attachments'  => $attachments,
        ]);
    }

    /**
     * Send a template message (for channels that support it).
     */
    public function sendTemplate(int $conversationId, array $templateData): array
    {
        $this->validateRequiredFields($templateData, ['template_params']);

        return $this->send($conversationId, [
            'content'         => $templateData['content'] ?? '',
            'message_type'    => 'outgoing',
            'template_params' => $templateData['template_params'],
            'content_type'    => 'template',
        ]);
    }

    /**
     * Send an interactive message (buttons, quick replies, etc.).
     */
    public function sendInteractive(int $conversationId, array $interactiveData): array
    {
        $this->validateRequiredFields($interactiveData, ['content_attributes']);

        return $this->send($conversationId, [
            'content'            => $interactiveData['content'] ?? '',
            'message_type'       => 'outgoing',
            'content_type'       => 'interactive',
            'content_attributes' => $interactiveData['content_attributes'],
        ]);
    }

    /**
     * Send location message.
     */
    public function sendLocation(int $conversationId, float $latitude, float $longitude, ?string $name = null): array
    {
        $contentAttributes = [
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ];

        if ($name) {
            $contentAttributes['name'] = $name;
        }

        return $this->send($conversationId, [
            'content'            => $name ?? "Location: $latitude, $longitude",
            'message_type'       => 'outgoing',
            'content_type'       => 'location',
            'content_attributes' => $contentAttributes,
        ]);
    }

    /**
     * Delete a message.
     */
    public function delete(int $conversationId, int $messageId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/conversations/$conversationId/messages/$messageId";

        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Get a specific message.
     */
    public function get(int $conversationId, int $messageId): array
    {
        $messages = $this->list($conversationId);

        if (isset($messages['payload'])) {
            foreach ($messages['payload'] as $message) {
                if ($message['id'] == $messageId) {
                    return $message;
                }
            }
        }

        throw new \InvalidArgumentException("Message $messageId not found in conversation $conversationId");
    }

    /**
     * Search messages within a conversation.
     */
    public function search(int $conversationId, string $query): array
    {
        $messages = $this->list($conversationId);
        $results = [];

        if (isset($messages['payload'])) {
            foreach ($messages['payload'] as $message) {
                if (stripos($message['content'] ?? '', $query) !== false) {
                    $results[] = $message;
                }
            }
        }

        return $results;
    }

    /**
     * Get messages by type.
     */
    public function getByType(int $conversationId, string $messageType): array
    {
        $messages = $this->list($conversationId);
        $results = [];

        if (isset($messages['payload'])) {
            foreach ($messages['payload'] as $message) {
                if ($message['message_type'] === $messageType) {
                    $results[] = $message;
                }
            }
        }

        return $results;
    }

    /**
     * Get incoming messages only.
     */
    public function getIncoming(int $conversationId): array
    {
        return $this->getByType($conversationId, 'incoming');
    }

    /**
     * Get outgoing messages only.
     */
    public function getOutgoing(int $conversationId): array
    {
        return $this->getByType($conversationId, 'outgoing');
    }

    /**
     * Get private messages/notes only.
     */
    public function getPrivateNotes(int $conversationId): array
    {
        $messages = $this->list($conversationId);
        $results = [];

        if (isset($messages['payload'])) {
            foreach ($messages['payload'] as $message) {
                if ($message['private'] ?? false) {
                    $results[] = $message;
                }
            }
        }

        return $results;
    }

    /**
     * Get messages with attachments.
     */
    public function getWithAttachments(int $conversationId): array
    {
        $messages = $this->list($conversationId);
        $results = [];

        if (isset($messages['payload'])) {
            foreach ($messages['payload'] as $message) {
                if (! empty($message['attachments'])) {
                    $results[] = $message;
                }
            }
        }

        return $results;
    }

    /**
     * Get message statistics for conversation.
     */
    public function getStatistics(int $conversationId): array
    {
        $messages = $this->list($conversationId);

        if (! isset($messages['payload'])) {
            return [
                'total'            => 0,
                'incoming'         => 0,
                'outgoing'         => 0,
                'private'          => 0,
                'with_attachments' => 0,
            ];
        }

        $stats = [
            'total'            => count($messages['payload']),
            'incoming'         => 0,
            'outgoing'         => 0,
            'private'          => 0,
            'with_attachments' => 0,
        ];

        foreach ($messages['payload'] as $message) {
            if ($message['message_type'] === 'incoming') {
                $stats['incoming']++;
            } elseif ($message['message_type'] === 'outgoing') {
                $stats['outgoing']++;
            }

            if ($message['private'] ?? false) {
                $stats['private']++;
            }

            if (! empty($message['attachments'])) {
                $stats['with_attachments']++;
            }
        }

        return $stats;
    }

    /**
     * Mark message as read (if supported by channel).
     */
    public function markAsRead(int $conversationId, int $messageId): array
    {
        // This depends on the channel capabilities
        // For now, we'll return the message data
        return $this->get($conversationId, $messageId);
    }

    /**
     * Get latest message in conversation.
     */
    public function getLatest(int $conversationId): ?array
    {
        $messages = $this->list($conversationId);

        if (isset($messages['payload']) && ! empty($messages['payload'])) {
            // Messages are usually returned in chronological order, so get the last one
            return end($messages['payload']);
        }

        return null;
    }

    /**
     * Get message thread/replies.
     */
    public function getThread(int $conversationId, int $parentMessageId): array
    {
        // Chatwoot doesn't have native threading, but we can simulate by looking for related messages
        $messages = $this->list($conversationId);
        $thread = [];

        if (isset($messages['payload'])) {
            $parentFound = false;
            foreach ($messages['payload'] as $message) {
                if ($message['id'] == $parentMessageId) {
                    $parentFound = true;
                    $thread[] = $message;
                } elseif ($parentFound) {
                    // Get messages that came after the parent (simple threading)
                    $thread[] = $message;
                }
            }
        }

        return $thread;
    }

    /**
     * Bulk send messages to multiple conversations.
     */
    public function bulkSend(array $conversationMessages): array
    {
        $results = [];

        foreach ($conversationMessages as $item) {
            if (! isset($item['conversation_id']) || ! isset($item['message'])) {
                $results[] = [
                    'success' => false,
                    'error'   => 'conversation_id and message are required',
                    'data'    => $item,
                ];

                continue;
            }

            try {
                $result = $this->send($item['conversation_id'], $item['message']);
                $results[] = [
                    'success'         => true,
                    'conversation_id' => $item['conversation_id'],
                    'data'            => $result,
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'success'         => false,
                    'conversation_id' => $item['conversation_id'],
                    'error'           => $e->getMessage(),
                    'data'            => $item,
                ];
            }
        }

        return $results;
    }
}
