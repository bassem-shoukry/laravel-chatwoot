<?php

namespace BassamShoukry\LaravelChatwoot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $message;
    public array $conversation;
    public array $account;
    public array $changes;
    public array $sender;

    public function __construct(array $payload)
    {
        $this->message = $payload['message'] ?? [];
        $this->conversation = $payload['conversation'] ?? [];
        $this->account = $payload['account'] ?? [];
        $this->changes = $payload['changes'] ?? [];
        $this->sender = $payload['message']['sender'] ?? [];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chatwoot.account.' . ($this->account['id'] ?? 'unknown')),
            new PrivateChannel('chatwoot.conversation.' . ($this->conversation['id'] ?? 'unknown')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'      => $this->message['id'] ?? null,
            'conversation_id' => $this->conversation['id'] ?? null,
            'account_id'      => $this->account['id'] ?? null,
            'changes'         => $this->changes,
            'updated_at'      => now()->toISOString(),
        ];
    }

    public function getMessageId(): ?int
    {
        return $this->message['id'] ?? null;
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
