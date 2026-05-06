<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Models;

use Illuminate\Database\Eloquent\Model;

class ChatwootContact extends Model
{
    protected $table = 'chatwoot_contacts';

    /** @var list<string> */
    protected $fillable = [
        'account_name',
        'chatwoot_account_id',
        'contact_id',
        'name',
        'email',
        'phone_number',
        'identifier',
        'avatar_url',
        'additional_attributes',
        'custom_attributes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'chatwoot_account_id'   => 'integer',
            'contact_id'            => 'integer',
            'additional_attributes' => 'array',
            'custom_attributes'     => 'array',
        ];
    }
}
