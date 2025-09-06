<?php

namespace BassamShoukry\LaravelChatwoot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $conversation;
    public array $account;
    public array $contact;
    public array $inbox;

    public function __construct(array $payload)
    {
        $this->conversation = $payload['conversation'] ?? [];
        $this->account = $payload['account'] ?? [];
        $this->contact = $payload['conversation']['meta']['contact'] ?? [];
        $this->inbox = $payload['inbox'] ?? [];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chatwoot.account.' . ($this->account['id'] ?? 'unknown')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.created';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation['id'] ?? null,
            'account_id'      => $this->account['id'] ?? null,
            'contact_id'      => $this->contact['id'] ?? null,
            'inbox_id'        => $this->conversation['inbox_id'] ?? null,
            'status'          => $this->conversation['status'] ?? null,
            'created_at'      => $this->conversation['created_at'] ?? null,
        ];
    }

    public function getConversationId(): ?int
    {
        return $this->conversation['id'] ?? null;
    }

    public function getAccountId(): ?int
    {
        return $this->account['id'] ?? null;
    }

    public function getContactId(): ?int
    {
        return $this->contact['id'] ?? null;
    }

    public function getInboxId(): ?int
    {
        return $this->conversation['inbox_id'] ?? null;
    }

    public function getStatus(): ?string
    {
        return $this->conversation['status'] ?? null;
    }
}
