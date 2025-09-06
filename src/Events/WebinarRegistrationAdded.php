<?php

namespace BassamShoukry\LaravelChatwoot\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebinarRegistrationAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $registration;
    public array $account;
    public array $contact;

    public function __construct(array $payload)
    {
        $this->registration = $payload['registration'] ?? [];
        $this->account = $payload['account'] ?? [];
        $this->contact = $payload['contact'] ?? [];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chatwoot.account.' . ($this->account['id'] ?? 'unknown')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webinar.registration_added';
    }

    public function broadcastWith(): array
    {
        return [
            'registration_id' => $this->registration['id'] ?? null,
            'account_id'      => $this->account['id'] ?? null,
            'contact_id'      => $this->contact['id'] ?? null,
            'webinar_id'      => $this->registration['webinar_id'] ?? null,
            'registered_at'   => $this->registration['created_at'] ?? null,
        ];
    }

    public function getRegistrationId(): ?int
    {
        return $this->registration['id'] ?? null;
    }

    public function getAccountId(): ?int
    {
        return $this->account['id'] ?? null;
    }

    public function getContactId(): ?int
    {
        return $this->contact['id'] ?? null;
    }

    public function getWebinarId(): ?int
    {
        return $this->registration['webinar_id'] ?? null;
    }

    public function getRegistrationData(): array
    {
        return $this->registration;
    }
}
