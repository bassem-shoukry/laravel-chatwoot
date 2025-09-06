<?php

namespace BassamShoukry\LaravelChatwoot\Facades;

use BassamShoukry\LaravelChatwoot\LaravelChatwoot as LaravelChatwootService;
use Illuminate\Support\Facades\Facade;

/**
 * Laravel Chatwoot Facade for fluent API access.
 *
 * @method static LaravelChatwootService account(string $accountKey)                                                                                      Select account for subsequent operations
 * @method static LaravelChatwootService inbox(string $inboxKey)                                                                                          Select inbox for subsequent operations
 * @method static array                  sendMessage(array $messageData)                                                                                  Send a direct message
 * @method static array                  sendTemplate(string $templateKey, array $variables = [], array $options = [])                                    Send a template message
 * @method static array                  sendBulkMessages(array $messages)                                                                                Send bulk messages
 * @method static string                 queueMessage(array $messageData, int $delay = 0)                                                                 Queue a message for asynchronous sending
 * @method static string                 queueTemplate(string $templateKey, array $variables = [], array $options = [], int $delay = 0)                   Queue a template message
 * @method static string                 queueBulkMessages(array $messages, int $batchSize = null)                                                        Queue bulk messages for asynchronous processing
 * @method static array                  createConversation(array $contactData)                                                                           Create a conversation
 * @method static array                  getConversation(int $conversationId)                                                                             Get conversation details
 * @method static array                  getConversationMessages(int $conversationId)                                                                     Get messages for a conversation
 * @method static array                  getMessageStatistics(string $accountKey = null, string $inboxKey = null, int $days = 30)                         Get message sending statistics
 * @method static array                  getTemplates(string $accountKey = null, string $inboxKey = null)                                                 Get available templates
 * @method static array                  getTemplate(string $templateKey, string $accountKey = null, string $inboxKey = null)                             Get a specific template
 * @method static array                  processTemplate(string $templateKey, array $variables = [], string $accountKey = null, string $inboxKey = null)  Process template with variables
 * @method static array                  validateTemplate(string $templateKey, array $variables = [], string $accountKey = null, string $inboxKey = null) Validate template
 * @method static bool                   checkRateLimit(string $accountKey = null, string $inboxKey = null)                                               Check rate limits for current account/inbox
 * @method static array                  getRateLimitInfo(string $accountKey = null, string $inboxKey = null)                                             Get rate limit information
 * @method static array                  getWebhookStatistics(string $accountId = null, int $days = 30)                                                   Get webhook statistics
 * @method static array                  getCurrentAccount()                                                                                              Get current account information
 * @method static array                  getCurrentInbox()                                                                                                Get current inbox information
 * @method static array                  getRoutingInfo()                                                                                                 Get routing information for current context
 * @method static array                  testConnection(string $accountKey = null)                                                                        Test connection to Chatwoot API
 * @method static LaravelChatwootService for(string $accountKey, string $inboxKey = null)                                                                 Fluent method chaining for account and inbox selection
 * @method static array                  send(string $content, array $contactData, array $options = [])                                                   Quick send method for simple messages
 * @method static array                  template(string $templateKey, array $variables = [], array $contactData = [], array $options = [])               Quick template send method
 *
 * @see LaravelChatwootService
 */
class LaravelChatwoot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-chatwoot';
    }
}
