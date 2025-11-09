# Laravel Chatwoot

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bassamshoukry/laravel-chatwoot.svg?style=flat-square)](https://packagist.org/packages/bassamshoukry/laravel-chatwoot)
[![Total Downloads](https://img.shields.io/packagist/dt/bassamshoukry/laravel-chatwoot.svg?style=flat-square)](https://packagist.org/packages/bassamshoukry/laravel-chatwoot)

A comprehensive Laravel package for integrating with the Chatwoot API. Supports multi-account management, conversations, contacts, messages, webhooks, and template messaging.

## Features

- **Multi-Account Support**: Manage multiple Chatwoot accounts from a single Laravel application
- **Multi-Inbox Routing**: Route messages to different inboxes within accounts
- **Comprehensive API Coverage**:
  - Conversations (create, list, update, search, filters)
  - Messages (send, receive, list, bulk operations)
  - Contacts (CRUD, search, merge, custom attributes)
  - Labels (create, list, update, delete, assign)
  - Accounts & Inboxes management
- **Webhook Handling**: Receive and process Chatwoot webhook events
- **Template System**: Store and process message templates
- **Queue Support**: Async message sending with Laravel queues
- **Rate Limiting**: Client-side rate limit tracking per inbox
- **Pagination**: Generic pagination helpers for all API endpoints
- **Database Tracking**: Optional local storage of conversations, messages, and contacts
- **Artisan Commands**: CLI tools for connection testing, template sync, and status checking

## Requirements

- PHP ^8.4
- Laravel ^11.0 || ^12.0
- Chatwoot instance (cloud or self-hosted)

## Installation

Install the package via Composer:

```bash
composer require bassamshoukry/laravel-chatwoot
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="chatwoot-config"
```

(Optional) Publish and run migrations if you want local database tracking:

```bash
php artisan vendor:publish --tag="chatwoot-migrations"
php artisan migrate
```

## Configuration

### Basic Setup

Edit `config/chatwoot.php` and configure your Chatwoot accounts:

```php
return [
    'accounts' => [
        'primary' => [
            'url' => env('CHATWOOT_URL', 'https://app.chatwoot.com'),
            'api_token' => env('CHATWOOT_API_TOKEN'),
            'account_id' => env('CHATWOOT_ACCOUNT_ID'),
            'inboxes' => [
                'support' => [
                    'inbox_id' => env('CHATWOOT_INBOX_ID'),
                    'channels' => ['website', 'api'],
                ],
            ],
        ],
    ],
];
```

### Multi-Account Configuration

```php
'accounts' => [
    'sales' => [
        'url' => env('CHATWOOT_SALES_URL'),
        'api_token' => env('CHATWOOT_SALES_TOKEN'),
        'account_id' => env('CHATWOOT_SALES_ACCOUNT_ID'),
        'inboxes' => [
            'website' => [
                'inbox_id' => 123,
                'channels' => ['website'],
            ],
            'whatsapp' => [
                'inbox_id' => 456,
                'channels' => ['whatsapp'],
            ],
        ],
    ],
    'support' => [
        'url' => env('CHATWOOT_SUPPORT_URL'),
        'api_token' => env('CHATWOOT_SUPPORT_TOKEN'),
        'account_id' => env('CHATWOOT_SUPPORT_ACCOUNT_ID'),
        'inboxes' => [
            'email' => [
                'inbox_id' => 789,
                'channels' => ['email'],
            ],
        ],
    ],
],
```

## Usage

### Basic Usage

```php
use BassamShoukry\LaravelChatwoot\Facades\LaravelChatwoot;

// Set account context
LaravelChatwoot::account('primary')->inbox('support');

// Or use the fluent API
LaravelChatwoot::for('primary', 'support');
```

### Conversations

```php
// List conversations
$conversations = LaravelChatwoot::conversations()->list();

// Get paginated conversations
$page1 = LaravelChatwoot::conversations()->getPaginated(page: 1, perPage: 25);

// Get all conversations (auto-pagination)
$allConversations = LaravelChatwoot::conversations()->getAll();

// Create a conversation
$conversation = LaravelChatwoot::conversations()->create([
    'source_id' => 'unique-source-id',
    'inbox_id' => 123,
    'contact_id' => 456,
]);

// Get specific conversation
$conversation = LaravelChatwoot::conversations()->get($conversationId);

// Update conversation status
LaravelChatwoot::conversations()->updateStatus($conversationId, 'resolved');

// Assign to agent
LaravelChatwoot::conversations()->assign($conversationId, $agentId);

// Add labels
LaravelChatwoot::conversations()->addLabels($conversationId, ['urgent', 'billing']);

// Search conversations
$results = LaravelChatwoot::conversations()->search('customer query');
```

### Messages

```php
// Send a message
$message = LaravelChatwoot::messages()->send($conversationId, [
    'content' => 'Hello, how can we help you?',
    'message_type' => 'outgoing',
]);

// Send message with queue
LaravelChatwoot::queueMessage($conversationId, [
    'content' => 'This will be sent via queue',
]);

// Get messages for a conversation
$messages = LaravelChatwoot::conversations()->getMessages($conversationId);

// Send template message
LaravelChatwoot::sendTemplate('welcome_template', [
    'customer_name' => 'John Doe',
], [
    'conversation_id' => $conversationId,
]);

// Bulk send messages
LaravelChatwoot::sendBulkMessages($conversationIds, [
    'content' => 'Broadcast message',
]);
```

### Contacts

```php
// List contacts
$contacts = LaravelChatwoot::contacts()->list();

// Get paginated contacts
$page1 = LaravelChatwoot::contacts()->getPaginated(page: 1, perPage: 50);

// Get all contacts (auto-pagination)
$allContacts = LaravelChatwoot::contacts()->getAll();

// Create a contact
$contact = LaravelChatwoot::contacts()->create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone_number' => '+1234567890',
]);

// Update contact
LaravelChatwoot::contacts()->update($contactId, [
    'name' => 'Jane Doe',
]);

// Search contacts
$results = LaravelChatwoot::contacts()->search('john@example.com');

// Find or create contact
$contact = LaravelChatwoot::contacts()->findOrCreate([
    'identifier' => 'john@example.com',
    'name' => 'John Doe',
]);

// Merge contacts
LaravelChatwoot::contacts()->merge($primaryContactId, $duplicateContactId);

// Add labels
LaravelChatwoot::contacts()->addLabels($contactId, ['vip', 'premium']);
```

### Labels

```php
// List all labels
$labels = LaravelChatwoot::accounts()->getLabels();

// Create a label
$label = LaravelChatwoot::accounts()->createLabel([
    'title' => 'VIP Customer',
    'description' => 'High-value customers',
    'color' => '#FF6B6B',
]);

// Update label
LaravelChatwoot::accounts()->updateLabel($labelId, [
    'title' => 'Premium Customer',
]);

// Delete label
LaravelChatwoot::accounts()->deleteLabel($labelId);
```

### Webhooks

#### Setup Webhook Route

The package automatically registers webhook routes. Configure your Chatwoot instance to send webhooks to:

```
https://your-app.com/api/chatwoot/webhook
```

#### Enable Webhook Signature Verification

In `config/chatwoot.php`:

```php
'webhooks' => [
    'enabled' => true,
    'verify_signature' => true,
    'secret' => env('CHATWOOT_WEBHOOK_SECRET'),
    'fire_events' => true,
],
```

#### Listen to Webhook Events

```php
use BassamShoukry\LaravelChatwoot\Events\MessageCreated;

class HandleNewMessage
{
    public function handle(MessageCreated $event)
    {
        $message = $event->message;
        $conversation = $event->conversation;
        $account = $event->account;

        // Your logic here
    }
}
```

Available events:
- `ConversationCreated`
- `ConversationUpdated`
- `ConversationStatusChanged`
- `MessageCreated`
- `MessageUpdated`
- `ContactCreated`
- `ContactUpdated`

### Templates

#### Store Templates

```php
// In your application
LaravelChatwoot::templates()->store('welcome_message', [
    'content' => [
        'text' => 'Hello {{customer_name}}, welcome to our service!',
        'type' => 'text',
    ],
], 'primary');
```

#### Sync Templates from Files

```bash
php artisan chatwoot:sync-templates primary --source=file
```

#### Send Template Message

```php
LaravelChatwoot::account('primary')
    ->inbox('support')
    ->sendTemplate('welcome_message', [
        'customer_name' => 'John Doe',
    ], [
        'contact' => [
            'identifier' => 'john@example.com',
            'name' => 'John Doe',
        ],
    ]);
```

## Artisan Commands

### Check Package Status

```bash
php artisan chatwoot --status
```

### List Configured Accounts

```bash
php artisan chatwoot --accounts
```

### Test API Connection

```bash
# Test all accounts
php artisan chatwoot:test-connection

# Test specific account
php artisan chatwoot:test-connection primary

# Detailed output
php artisan chatwoot:test-connection --detailed
```

### Send Template Message

```bash
php artisan chatwoot:send-template primary support welcome_template john@example.com \
    --variables='{"name":"John"}' \
    --contact-name="John Doe"
```

### Sync Templates

```bash
# Sync from files
php artisan chatwoot:sync-templates primary --source=file

# Sync with validation
php artisan chatwoot:sync-templates --source=file --validate

# Dry run
php artisan chatwoot:sync-templates --source=file --dry-run
```

## Queue Configuration

Enable queue processing for async message sending:

```php
'queue' => [
    'enabled' => true,
    'connection' => env('QUEUE_CONNECTION', 'redis'),
    'queue' => env('CHATWOOT_QUEUE_NAME', 'chatwoot'),
],
```

## Database Tracking

The package can optionally track conversations, messages, contacts, and webhook events in your local database:

```php
'webhooks' => [
    'track_conversations' => true,
    'track_messages' => true,
    'track_contacts' => true,
],
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Bassem Shoukry](https://github.com/bassamshoukry)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Resources

- [Chatwoot API Documentation](https://developers.chatwoot.com/api-reference/introduction)
- [Package Documentation](https://github.com/bassamshoukry/laravel-chatwoot)
