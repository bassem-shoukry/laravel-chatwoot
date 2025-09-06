<?php

namespace BassamShoukry\LaravelChatwoot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $contact;
    public array $account;

    public function __construct(array $payload)
    {
        $this->contact = $payload['contact'] ?? [];
        $this->account = $payload['account'] ?? [];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chatwoot.account.' . ($this->account['id'] ?? 'unknown')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'contact.created';
    }

    public function broadcastWith(): array
    {
        return [
            'contact_id'   => $this->contact['id'] ?? null,
            'account_id'   => $this->account['id'] ?? null,
            'name'         => $this->contact['name'] ?? null,
            'email'        => $this->contact['email'] ?? null,
            'phone_number' => $this->contact['phone_number'] ?? null,
            'identifier'   => $this->contact['identifier'] ?? null,
            'created_at'   => $this->contact['created_at'] ?? null,
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

    public function getName(): ?string
    {
        return $this->contact['name'] ?? null;
    }

    public function getEmail(): ?string
    {
        return $this->contact['email'] ?? null;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->contact['phone_number'] ?? null;
    }

    public function getIdentifier(): ?string
    {
        return $this->contact['identifier'] ?? null;
    }

    public function getCustomAttributes(): array
    {
        return $this->contact['custom_attributes'] ?? [];
    }
}
