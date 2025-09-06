<?php

namespace BassamShoukry\LaravelChatwoot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $contact;
    public array $account;
    public array $changes;

    public function __construct(array $payload)
    {
        $this->contact = $payload['contact'] ?? [];
        $this->account = $payload['account'] ?? [];
        $this->changes = $payload['changes'] ?? [];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chatwoot.account.' . ($this->account['id'] ?? 'unknown')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'contact.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'contact_id' => $this->contact['id'] ?? null,
            'account_id' => $this->account['id'] ?? null,
            'name'       => $this->contact['name'] ?? null,
            'email'      => $this->contact['email'] ?? null,
            'changes'    => $this->changes,
            'updated_at' => now()->toISOString(),
        ];
    }

    public function getContactId(): ?int
    {
        return $this->contact['id'] ?? null;
    }

    public function getAccountId(): ?int
    {
        return $this->account['id'] ?? null;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function getName(): ?string
    {
        return $this->contact['name'] ?? null;
    }

    public function getEmail(): ?string
    {
        return $this->contact['email'] ?? null;
    }
}
