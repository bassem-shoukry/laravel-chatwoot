<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Models;

use Illuminate\Database\Eloquent\Model;

class ChatwootMessage extends Model
{
    protected $table = 'chatwoot_messages';

    /** @var list<string> */
    protected $fillable = [
        'account_name',
        'chatwoot_account_id',
        'message_id',
        'conversation_id',
        'inbox_id',
        'message_type',
        'content_type',
        'content',
        'private',
        'sender_id',
        'sender_type',
        'content_attributes',
        'attachments',
        'chatwoot_created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'chatwoot_account_id' => 'integer',
            'message_id'          => 'integer',
            'conversation_id'     => 'integer',
            'inbox_id'            => 'integer',
            'message_type'        => 'integer',
            'private'             => 'boolean',
            'sender_id'           => 'integer',
            'content_attributes'  => 'array',
            'attachments'         => 'array',
            'chatwoot_created_at' => 'datetime',
        ];
    }
}
