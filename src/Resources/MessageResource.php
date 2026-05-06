<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Resources;

use BassamShoukry\LaravelChatwoot\Data\Message;
use BassamShoukry\LaravelChatwoot\Enums\ContentType;
use BassamShoukry\LaravelChatwoot\Enums\MessageType;
use Illuminate\Support\Collection;

final class MessageResource extends BaseResource
{
    /**
     * @return Collection<int, Message>
     */
    public function listForConversation(int $conversationId): Collection
    {
        $response = $this->client->get($this->accountPath("conversations/{$conversationId}/messages"));
        $payload = $response['payload'] ?? $response['data'] ?? [];

        return collect($this->arrayOfArrays(is_array($payload) ? $payload : []))
            ->map(static fn (array $row): Message => Message::from($row));
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function send(
        int $conversationId,
        string $content,
        MessageType $type = MessageType::Outgoing,
        ContentType $contentType = ContentType::Text,
        bool $private = false,
        array $extra = [],
    ): Message {
        $payload = array_merge([
            'content'      => $content,
            'message_type' => $type->value,
            'content_type' => $contentType->value,
            'private'      => $private,
        ], $extra);

        $response = $this->client->post($this->accountPath("conversations/{$conversationId}/messages"), $payload);

        return Message::from($response);
    }

    /**
     * @param array<int, array{title: string, value?: string}> $buttons
     */
    public function sendInteractiveButtons(int $conversationId, string $body, array $buttons): Message
    {
        return $this->send(
            conversationId: $conversationId,
            content: $body,
            contentType: ContentType::InputSelect,
            extra: [
                'content_attributes' => [
                    'items' => array_map(
                        static fn (array $b): array => [
                            'title' => (string) $b['title'],
                            'value' => (string) ($b['value'] ?? $b['title']),
                        ],
                        array_values($buttons),
                    ),
                ],
            ],
        );
    }

    /**
     * @param array<int, array{title: string, items: array<int, array{title: string, value?: string, description?: string}>}> $sections
     */
    public function sendInteractiveList(int $conversationId, string $body, array $sections): Message
    {
        $items = [];
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $items[] = [
                    'title'       => (string) $item['title'],
                    'value'       => (string) ($item['value'] ?? $item['title']),
                    'description' => isset($item['description']) ? (string) $item['description'] : null,
                ];
            }
        }

        return $this->send(
            conversationId: $conversationId,
            content: $body,
            contentType: ContentType::InputSelect,
            extra: ['content_attributes' => ['items' => $items]],
        );
    }

    /**
     * @param array<int, mixed> $components
     */
    public function sendTemplate(
        int $conversationId,
        string $name,
        string $language,
        array $components = [],
    ): Message {
        return $this->send(
            conversationId: $conversationId,
            content: '',
            type: MessageType::Outgoing,
            contentType: ContentType::Text,
            extra: [
                'template_params' => [
                    'name'             => $name,
                    'category'         => 'utility',
                    'language'         => $language,
                    'processed_params' => (object) [],
                    'components'       => $components,
                ],
            ],
        );
    }

    /**
     * @param array<string, mixed> $contentAttributes
     * @param array<string, mixed> $extra
     */
    public function sendRaw(
        int $conversationId,
        array $contentAttributes,
        string $content = '',
        ContentType $contentType = ContentType::Text,
        array $extra = [],
    ): Message {
        return $this->send(
            conversationId: $conversationId,
            content: $content,
            contentType: $contentType,
            extra: array_merge(['content_attributes' => $contentAttributes], $extra),
        );
    }

    public function delete(int $conversationId, int $messageId): void
    {
        $this->client->delete($this->accountPath("conversations/{$conversationId}/messages/{$messageId}"));
    }
}
