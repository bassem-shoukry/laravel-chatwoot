<?php

namespace BassamShoukry\LaravelChatwoot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $message;
    public array $conversation;
    public array $account;
    public array $contact;
    public array $sender;

    public function __construct(array $payload)
    {
        $this->message = $payload['message'] ?? [];
        $this->conversation = $payload['conversation'] ?? [];
        $this->account = $payload['account'] ?? [];
        $this->contact = $payload['conversation']['meta']['contact'] ?? [];
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
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'      => $this->message['id'] ?? null,
            'conversation_id' => $this->conversation['id'] ?? null,
            'account_id'      => $this->account['id'] ?? null,
            'contact_id'      => $this->contact['id'] ?? null,
            'sender_type'     => $this->sender['type'] ?? null,
            'sender_id'       => $this->sender['id'] ?? null,
            'message_type'    => $this->message['message_type'] ?? null,
            'content'         => $this->message['content'] ?? null,
            'created_at'      => $this->message['created_at'] ?? null,
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

    public function getContent(): ?string
    {
        return $this->message['content'] ?? null;
    }

    public function getMessageType(): ?string
    {
        return $this->message['message_type'] ?? null;
    }

    public function getSenderType(): ?string
    {
        return $this->sender['type'] ?? null;
    }

    public function isFromContact(): bool
    {
        return $this->getSenderType() === 'contact';
    }

    public function isFromAgent(): bool
    {
        return in_array($this->getSenderType(), ['user', 'agent']);
    }

    public function isFromBot(): bool
    {
        return $this->getSenderType() === 'agent_bot';
    }

    public function isIncoming(): bool
    {
        return $this->getMessageType() === 'incoming';
    }

    public function isOutgoing(): bool
    {
        return $this->getMessageType() === 'outgoing';
    }
}
