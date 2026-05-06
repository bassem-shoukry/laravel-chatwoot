<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Resources;

use BassamShoukry\LaravelChatwoot\Data\Conversation;
use BassamShoukry\LaravelChatwoot\Enums\ConversationStatus;
use Illuminate\Support\Collection;

final class ConversationResource extends BaseResource
{
    /**
     * @param array<string, mixed> $filters
     *
     * @return Collection<int, Conversation>
     */
    public function list(array $filters = []): Collection
    {
        $response = $this->client->get($this->accountPath('conversations'), $filters);
        $payload = $response['data']['payload'] ?? $response['payload'] ?? [];

        return collect($this->arrayOfArrays(is_array($payload) ? $payload : []))
            ->map(static fn (array $row): Conversation => Conversation::from($row));
    }

    public function find(int $conversationId): Conversation
    {
        $response = $this->client->get($this->accountPath("conversations/{$conversationId}"));

        return Conversation::from($response);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function create(int $inboxId, int $contactId, ?string $sourceId = null, array $extra = []): Conversation
    {
        $payload = array_merge([
            'inbox_id'   => $inboxId,
            'contact_id' => $contactId,
        ], $extra);

        if ($sourceId !== null) {
            $payload['source_id'] = $sourceId;
        }

        $response = $this->client->post($this->accountPath('conversations'), $payload);

        return Conversation::from($response);
    }

    public function firstOrCreateForContact(int $contactId, int $inboxId, ?string $sourceId = null): Conversation
    {
        $list = $this->client->get($this->accountPath("contacts/{$contactId}/conversations"));
        $payload = $list['payload'] ?? [];
        if (is_array($payload)) {
            foreach ($this->arrayOfArrays($payload) as $row) {
                if ((int) ($row['inbox_id'] ?? 0) === $inboxId
                    && in_array((string) ($row['status'] ?? 'open'), ['open', 'pending', 'snoozed'], true)) {
                    return Conversation::from($row);
                }
            }
        }

        return $this->create($inboxId, $contactId, $sourceId);
    }

    public function updateStatus(int $conversationId, ConversationStatus $status): Conversation
    {
        $response = $this->client->post(
            $this->accountPath("conversations/{$conversationId}/toggle_status"),
            ['status' => $status->value],
        );

        return Conversation::from(is_array($response['payload'] ?? null) ? $response['payload'] : $response);
    }

    public function assignAgent(int $conversationId, ?int $agentId): void
    {
        $this->client->post(
            $this->accountPath("conversations/{$conversationId}/assignments"),
            ['assignee_id' => $agentId],
        );
    }

    public function assignTeam(int $conversationId, ?int $teamId): void
    {
        $this->client->post(
            $this->accountPath("conversations/{$conversationId}/assignments"),
            ['team_id' => $teamId],
        );
    }

    /**
     * @param array<int, string> $labels
     */
    public function setLabels(int $conversationId, array $labels): void
    {
        $this->client->post(
            $this->accountPath("conversations/{$conversationId}/labels"),
            ['labels' => $labels],
        );
    }

    public function toggleTyping(int $conversationId, bool $on): void
    {
        $this->client->post(
            $this->accountPath("conversations/{$conversationId}/toggle_typing_status"),
            ['typing_status' => $on ? 'on' : 'off'],
        );
    }
}
