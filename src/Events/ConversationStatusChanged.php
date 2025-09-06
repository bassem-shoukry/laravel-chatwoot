<?php

namespace BassamShoukry\LaravelChatwoot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $conversation;
    public array $account;
    public ?string $oldStatus;
    public ?string $newStatus;
    public array $contact;

    public function __construct(array $payload)
    {
        $this->conversation = $payload['conversation'] ?? [];
        $this->account = $payload['account'] ?? [];
        $this->oldStatus = $payload['meta']['old_status'] ?? null;
        $this->newStatus = $payload['conversation']['status'] ?? null;
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
        return 'conversation.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation['id'] ?? null,
            'account_id'      => $this->account['id'] ?? null,
            'contact_id'      => $this->contact['id'] ?? null,
            'old_status'      => $this->oldStatus,
            'new_status'      => $this->newStatus,
            'changed_at'      => now()->toISOString(),
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

    public function getOldStatus(): ?string
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): ?string
    {
        return $this->newStatus;
    }

    public function wasResolved(): bool
    {
        return $this->newStatus === 'resolved';
    }

    public function wasReopened(): bool
    {
        return $this->oldStatus === 'resolved' && $this->newStatus === 'open';
    }
}
