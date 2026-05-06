<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Models;

use Illuminate\Database\Eloquent\Model;

class ChatwootWebhookEvent extends Model
{
    protected $table = 'chatwoot_webhook_events';

    /** @var list<string> */
    protected $fillable = [
        'account_name',
        'event',
        'payload',
        'verified',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload'  => 'array',
            'verified' => 'boolean',
        ];
    }
}
