<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Models;

use Illuminate\Database\Eloquent\Model;

class ChatwootConversation extends Model
{
    protected $table = 'chatwoot_conversations';

    /** @var list<string> */
    protected $fillable = [
        'account_name',
        'chatwoot_account_id',
        'conversation_id',
        'inbox_id',
        'contact_id',
        'status',
        'assignee_id',
        'team_id',
        'labels',
        'additional_attributes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'chatwoot_account_id'   => 'integer',
            'conversation_id'       => 'integer',
            'inbox_id'              => 'integer',
            'contact_id'            => 'integer',
            'assignee_id'           => 'integer',
            'team_id'               => 'integer',
            'labels'                => 'array',
            'additional_attributes' => 'array',
        ];
    }
}
