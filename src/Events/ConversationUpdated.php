<?php

namespace BassamShoukry\LaravelChatwoot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $conversation;
    public array $account;
    public array $changes;
    public array $contact;

    public function __construct(array $payload)
    {
        $this->conversation = $payload['conversation'] ?? [];
        $this->account = $payload['account'] ?? [];
        $this->changes = $payload['changes'] ?? [];
        $this->contact = $payload['conversation']['meta']['contact'] ?? [];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chatwoot.account.' . ($this->account['id'] ?? 'unknown')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation['id'] ?? null,
            'account_id'      => $this->account['id'] ?? null,
            'contact_id'      => $this->contact['id'] ?? null,
            'changes'         => $this->changes,
            'status'          => $this->conversation['status'] ?? null,
            'updated_at'      => now()->toISOString(),
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

    public function getChanges(): array
    {
        return $this->changes;
    }
}
