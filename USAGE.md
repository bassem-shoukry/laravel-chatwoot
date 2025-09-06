# Laravel Chatwoot Package Usage Guide

## Organized API Structure

The Laravel Chatwoot package now features a well-organized API structure with dedicated classes for different Chatwoot API endpoints:

### API Service Classes

- **`AccountsApi`** - Account management, users, teams, labels, webhooks, reports
- **`ContactsApi`** - Contact management, search, custom attributes, labels
- **`ConversationsApi`** - Conversation management, status updates, assignments, messages
- **`InboxesApi`** - Inbox management, agents, campaigns, settings
- **`MessagesApi`** - Message sending, attachments, templates, interactions

## Basic Usage

### Using Individual API Classes

```php
use BassamShoukry\LaravelChatwoot\Services\Api\ContactsApi;
use BassamShoukry\LaravelChatwoot\Services\Api\ConversationsApi;
use BassamShoukry\LaravelChatwoot\Services\Api\MessagesApi;

// Set account context first
app(\BassamShoukry\LaravelChatwoot\Services\AccountManager::class)->account('primary');

// Work with contacts
$contactsApi = app(ContactsApi::class);
$contact = $contactsApi->findOrCreate([
    'identifier' => 'customer123',
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Create conversation
$conversationsApi = app(ConversationsApi::class);
$conversation = $conversationsApi->create([
    'source_id' => 'customer123',
    'inbox_id' => 1,
    'contact_id' => $contact['id']
]);

// Send message
$messagesApi = app(MessagesApi::class);
$message = $messagesApi->sendText($conversation['id'], 'Hello! How can we help you?');
```

### Using the Unified ApiClient

```php
use BassamShoukry\LaravelChatwoot\Services\ApiClient;

$apiClient = app(ApiClient::class);

// Set account context
app(\BassamShoukry\LaravelChatwoot\Services\AccountManager::class)->account('primary');

// Access organized APIs through the client
$contacts = $apiClient->contacts->list(['page' => 1]);
$conversations = $apiClient->conversations->getByStatus('open');
$inboxes = $apiClient->inboxes->getWebsiteInboxes();
```

### Using the Facade

```php
use BassamShoukry\LaravelChatwoot\Facades\LaravelChatwoot;

// Quick message sending
LaravelChatwoot::account('primary')
    ->inbox('support')
    ->send('Hello!', ['identifier' => 'customer123', 'name' => 'John']);

// Template messaging
LaravelChatwoot::for('primary', 'sales')
    ->template('welcome', ['name' => 'John'], ['email' => 'john@example.com']);
```

## Detailed Examples

### Contact Management

```php
$contactsApi = app(ContactsApi::class);

// Search for contacts
$results = $contactsApi->search('john@example.com');

// Create with custom attributes
$contact = $contactsApi->create([
    'name' => 'Jane Smith',
    'email' => 'jane@example.com',
    'phone_number' => '+1234567890',
    'custom_attributes' => [
        'plan' => 'premium',
        'signup_date' => '2024-01-15'
    ]
]);

// Update custom attributes
$contactsApi->updateCustomAttributes($contact['id'], [
    'plan' => 'enterprise',
    'last_login' => now()->toISOString()
]);

// Get contact conversations
$conversations = $contactsApi->getConversations($contact['id']);

// Bulk update contacts
$updates = [
    ['id' => 1, 'custom_attributes' => ['status' => 'active']],
    ['id' => 2, 'custom_attributes' => ['status' => 'inactive']]
];
$results = $contactsApi->bulkUpdate($updates);
```

### Conversation Management

```php
$conversationsApi = app(ConversationsApi::class);

// Get conversations by different criteria
$openConversations = $conversationsApi->getByStatus('open');
$assignedToMe = $conversationsApi->getByAssignee(123);
$inboxConversations = $conversationsApi->getByInbox(1);

// Update conversation
$conversationsApi->updateStatus($conversationId, 'resolved');
$conversationsApi->assign($conversationId, $agentId);
$conversationsApi->addLabels($conversationId, ['urgent', 'billing']);

// Send messages
$messagesApi = app(MessagesApi::class);
$messagesApi->sendText($conversationId, 'Thank you for contacting us!');
$messagesApi->sendPrivateNote($conversationId, 'Customer seems frustrated');

// Send interactive message
$messagesApi->sendInteractive($conversationId, [
    'content' => 'How would you rate our service?',
    'content_attributes' => [
        'type' => 'buttons',
        'buttons' => [
            ['title' => 'Excellent', 'payload' => 'rating_5'],
            ['title' => 'Good', 'payload' => 'rating_4'],
            ['title' => 'Average', 'payload' => 'rating_3']
        ]
    ]
]);
```

### Inbox Management

```php
$inboxesApi = app(InboxesApi::class);

// Get all inboxes
$inboxes = $inboxesApi->list();

// Get inboxes by type
$whatsappInboxes = $inboxesApi->getWhatsAppInboxes();
$websiteInboxes = $inboxesApi->getWebsiteInboxes();

// Create API inbox
$apiInbox = $inboxesApi->createApiInbox([
    'name' => 'Customer Support API',
    'greeting_enabled' => true,
    'greeting_message' => 'Welcome! How can we help?'
]);

// Manage inbox agents
$inboxesApi->addAgent($inboxId, $agentId);
$inboxesApi->updateAgents($inboxId, [123, 456, 789]);

// Update inbox settings
$inboxesApi->updateSettings($inboxId, [
    'greeting_enabled' => true,
    'working_hours_enabled' => true,
    'csat_survey_enabled' => true
]);

// Get performance metrics
$metrics = $inboxesApi->getPerformanceMetrics($inboxId, [
    'since' => '2024-01-01',
    'until' => '2024-01-31'
]);
```

### Account Management

```php
$accountsApi = app(AccountsApi::class);

// Get account details
$account = $accountsApi->get();

// Manage users
$users = $accountsApi->getUsers();
$newUser = $accountsApi->createUser([
    'name' => 'New Agent',
    'email' => 'agent@example.com',
    'role' => 'agent'
]);

// Manage teams
$teams = $accountsApi->getTeams();
$newTeam = $accountsApi->createTeam([
    'name' => 'Customer Success',
    'description' => 'Handles customer onboarding and success'
]);

$accountsApi->addAgentsToTeam($newTeam['id'], [123, 456]);

// Get reports and statistics
$conversationReports = $accountsApi->getConversationReports([
    'since' => '2024-01-01',
    'until' => '2024-01-31'
]);

$usageStats = $accountsApi->getUsageStats();
```

### Advanced Message Operations

```php
$messagesApi = app(MessagesApi::class);

// Send with attachments
$messagesApi->sendWithAttachments($conversationId, 'Please find attached files', [
    ['url' => 'https://example.com/file.pdf', 'type' => 'file']
]);

// Send location
$messagesApi->sendLocation($conversationId, 40.7128, -74.0060, 'New York Office');

// Send template message (for WhatsApp)
$messagesApi->sendTemplate($conversationId, [
    'template_params' => [
        'name' => 'order_confirmation',
        'language' => ['code' => 'en'],
        'components' => [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => 'John Doe'],
                    ['type' => 'text', 'text' => '#12345']
                ]
            ]
        ]
    ]
]);

// Get message statistics
$stats = $messagesApi->getStatistics($conversationId);

// Bulk send to multiple conversations
$bulkMessages = [
    ['conversation_id' => 1, 'message' => ['content' => 'Hello from conversation 1', 'message_type' => 'outgoing']],
    ['conversation_id' => 2, 'message' => ['content' => 'Hello from conversation 2', 'message_type' => 'outgoing']]
];
$results = $messagesApi->bulkSend($bulkMessages);
```

## Error Handling

All API classes extend `BaseApiService` which provides consistent error handling:

```php
try {
    $contact = $contactsApi->create($contactData);
} catch (\BassamShoukry\LaravelChatwoot\Exceptions\ChatwootApiException $e) {
    Log::error('Chatwoot API error: ' . $e->getMessage(), [
        'status_code' => $e->getStatusCode(),
        'response_data' => $e->getResponseData()
    ]);
    
    // Handle specific error cases
    if ($e->getStatusCode() === 422) {
        // Validation error
        $errors = $e->getResponseData()['errors'] ?? [];
    }
}
```

## Benefits of the Organized Structure

1. **Domain Separation** - Each API class focuses on a specific domain (contacts, conversations, etc.)
2. **Method Organization** - Related methods are grouped together logically
3. **Type Safety** - Better IDE support and type hinting
4. **Easier Testing** - Mock individual API classes for unit testing
5. **Cleaner Code** - More readable and maintainable API calls
6. **Consistent Error Handling** - All API classes share the same error handling patterns
7. **Extensibility** - Easy to add new methods or entire API classes

This organized structure makes the Laravel Chatwoot package much more maintainable and developer-friendly while providing comprehensive access to all Chatwoot API functionality.