<?php

use BassamShoukry\LaravelChatwoot\LaravelChatwoot;
use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use BassamShoukry\LaravelChatwoot\Services\InboxManager;
use BassamShoukry\LaravelChatwoot\Services\MessageService;
use BassamShoukry\LaravelChatwoot\Services\RateLimitService;
use BassamShoukry\LaravelChatwoot\Services\TemplateService;
use BassamShoukry\LaravelChatwoot\Services\WebhookHandler;

beforeEach(function () {
    $this->accountManagerMock = Mockery::mock(AccountManager::class);
    $this->inboxManagerMock = Mockery::mock(InboxManager::class);
    $this->messageServiceMock = Mockery::mock(MessageService::class);
    $this->templateServiceMock = Mockery::mock(TemplateService::class);
    $this->rateLimitServiceMock = Mockery::mock(RateLimitService::class);
    $this->webhookHandlerMock = Mockery::mock(WebhookHandler::class);
    
    $this->chatwoot = new LaravelChatwoot(
        $this->accountManagerMock,
        $this->inboxManagerMock,
        $this->messageServiceMock,
        $this->templateServiceMock,
        $this->rateLimitServiceMock,
        $this->webhookHandlerMock
    );
});

describe('LaravelChatwoot Class', function () {
    it('can be instantiated with all dependencies', function () {
        expect($this->chatwoot)->toBeInstanceOf(LaravelChatwoot::class);
    });

    it('can be instantiated without dependencies', function () {
        $this->app->instance(AccountManager::class, $this->accountManagerMock);
        $this->app->instance(InboxManager::class, $this->inboxManagerMock);
        $this->app->instance(MessageService::class, $this->messageServiceMock);
        $this->app->instance(TemplateService::class, $this->templateServiceMock);
        $this->app->instance(RateLimitService::class, $this->rateLimitServiceMock);
        $this->app->instance(WebhookHandler::class, $this->webhookHandlerMock);

        $chatwoot = new LaravelChatwoot();
        
        expect($chatwoot)->toBeInstanceOf(LaravelChatwoot::class);
    });

    describe('Account Management', function () {
        it('can select an account', function () {
            $this->accountManagerMock
                ->shouldReceive('account')
                ->with('primary')
                ->once();

            $result = $this->chatwoot->account('primary');

            expect($result)->toBe($this->chatwoot);
        });

        it('can get current account information', function () {
            $expectedAccount = ['id' => 1, 'name' => 'Test Account'];
            
            $this->accountManagerMock
                ->shouldReceive('getCurrentAccountInfo')
                ->once()
                ->andReturn($expectedAccount);

            $result = $this->chatwoot->getCurrentAccount();

            expect($result)->toBe($expectedAccount);
        });

        it('can test connection to Chatwoot API', function () {
            $expectedResult = ['status' => 'connected', 'account_id' => 1];
            
            $this->accountManagerMock
                ->shouldReceive('getCurrentAccount')
                ->once()
                ->andReturn('primary');
            
            $this->accountManagerMock
                ->shouldReceive('testConnection')
                ->with('primary')
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->testConnection();

            expect($result)->toBe($expectedResult);
        });

        it('can test connection with specific account', function () {
            $expectedResult = ['status' => 'connected', 'account_id' => 2];
            
            $this->accountManagerMock
                ->shouldReceive('account')
                ->with('secondary')
                ->once();
            
            $this->accountManagerMock
                ->shouldReceive('testConnection')
                ->with('secondary')
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->testConnection('secondary');

            expect($result)->toBe($expectedResult);
        });
    });

    describe('Inbox Management', function () {
        it('can select an inbox', function () {
            $this->inboxManagerMock
                ->shouldReceive('inbox')
                ->with('support')
                ->once();

            $result = $this->chatwoot->inbox('support');

            expect($result)->toBe($this->chatwoot);
        });

        it('can get current inbox information', function () {
            $expectedInbox = ['id' => 1, 'name' => 'Support Inbox'];
            
            $this->inboxManagerMock
                ->shouldReceive('getCurrentInboxInfo')
                ->once()
                ->andReturn($expectedInbox);

            $result = $this->chatwoot->getCurrentInbox();

            expect($result)->toBe($expectedInbox);
        });

        it('can get routing information', function () {
            $expectedRouting = ['account_key' => 'primary', 'inbox_key' => 'support'];
            
            $this->inboxManagerMock
                ->shouldReceive('getRoutingInfo')
                ->once()
                ->andReturn($expectedRouting);

            $result = $this->chatwoot->getRoutingInfo();

            expect($result)->toBe($expectedRouting);
        });
    });

    describe('Message Management', function () {
        it('can send a direct message', function () {
            $messageData = [
                'content' => 'Hello World',
                'contact' => ['phone_number' => '+1234567890']
            ];
            $expectedResult = ['id' => 123, 'status' => 'sent'];

            $this->messageServiceMock
                ->shouldReceive('sendMessage')
                ->with($messageData)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->sendMessage($messageData);

            expect($result)->toBe($expectedResult);
        });

        it('can send a template message', function () {
            $templateKey = 'welcome';
            $variables = ['name' => 'John Doe'];
            $options = ['priority' => 'high'];
            $expectedResult = ['id' => 124, 'status' => 'sent'];

            $this->messageServiceMock
                ->shouldReceive('sendTemplate')
                ->with($templateKey, $variables, $options)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->sendTemplate($templateKey, $variables, $options);

            expect($result)->toBe($expectedResult);
        });

        it('can send bulk messages', function () {
            $messages = [
                ['content' => 'Message 1', 'contact' => ['phone' => '+1111111111']],
                ['content' => 'Message 2', 'contact' => ['phone' => '+2222222222']]
            ];
            $expectedResult = ['sent' => 2, 'failed' => 0];

            $this->messageServiceMock
                ->shouldReceive('sendBulkMessages')
                ->with($messages)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->sendBulkMessages($messages);

            expect($result)->toBe($expectedResult);
        });

        it('can queue a message', function () {
            $messageData = ['content' => 'Queued message'];
            $delay = 60;
            $expectedJobId = 'job_12345';

            $this->messageServiceMock
                ->shouldReceive('queueMessage')
                ->with($messageData, $delay)
                ->once()
                ->andReturn($expectedJobId);

            $result = $this->chatwoot->queueMessage($messageData, $delay);

            expect($result)->toBe($expectedJobId);
        });

        it('can queue a template message', function () {
            $templateKey = 'follow_up';
            $variables = ['name' => 'Jane'];
            $options = ['priority' => 'normal'];
            $delay = 120;
            $expectedJobId = 'job_12346';

            $this->messageServiceMock
                ->shouldReceive('queueTemplate')
                ->with($templateKey, $variables, $options, $delay)
                ->once()
                ->andReturn($expectedJobId);

            $result = $this->chatwoot->queueTemplate($templateKey, $variables, $options, $delay);

            expect($result)->toBe($expectedJobId);
        });

        it('can queue bulk messages', function () {
            $messages = [['content' => 'Bulk message']];
            $batchSize = 50;
            $expectedJobId = 'bulk_job_12347';

            $this->messageServiceMock
                ->shouldReceive('queueBulkMessages')
                ->with($messages, $batchSize)
                ->once()
                ->andReturn($expectedJobId);

            $result = $this->chatwoot->queueBulkMessages($messages, $batchSize);

            expect($result)->toBe($expectedJobId);
        });

        it('can create a conversation', function () {
            $contactData = ['name' => 'John Doe', 'email' => 'john@example.com'];
            $expectedResult = ['conversation_id' => 789, 'contact_id' => 456];

            $this->messageServiceMock
                ->shouldReceive('createConversation')
                ->with($contactData)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->createConversation($contactData);

            expect($result)->toBe($expectedResult);
        });

        it('can get conversation details', function () {
            $conversationId = 789;
            $expectedResult = ['id' => 789, 'status' => 'open', 'messages_count' => 5];

            $this->messageServiceMock
                ->shouldReceive('getConversation')
                ->with($conversationId)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->getConversation($conversationId);

            expect($result)->toBe($expectedResult);
        });

        it('can get conversation messages', function () {
            $conversationId = 789;
            $expectedResult = [
                ['id' => 1, 'content' => 'Hello'],
                ['id' => 2, 'content' => 'How can I help?']
            ];

            $this->messageServiceMock
                ->shouldReceive('getConversationMessages')
                ->with($conversationId)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->getConversationMessages($conversationId);

            expect($result)->toBe($expectedResult);
        });

        it('can get message statistics', function () {
            $accountKey = 'primary';
            $inboxKey = 'support';
            $days = 30;
            $expectedResult = ['sent' => 1500, 'delivered' => 1480, 'failed' => 20];

            $this->messageServiceMock
                ->shouldReceive('getMessageStatistics')
                ->with($accountKey, $inboxKey, $days)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->getMessageStatistics($accountKey, $inboxKey, $days);

            expect($result)->toBe($expectedResult);
        });
    });

    describe('Template Management', function () {
        it('can get available templates', function () {
            $accountKey = 'primary';
            $inboxKey = 'support';
            $expectedResult = [
                ['key' => 'welcome', 'name' => 'Welcome Message'],
                ['key' => 'follow_up', 'name' => 'Follow Up']
            ];

            $this->templateServiceMock
                ->shouldReceive('getAvailableTemplates')
                ->with($accountKey, $inboxKey)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->getTemplates($accountKey, $inboxKey);

            expect($result)->toBe($expectedResult);
        });

        it('can get a specific template', function () {
            $templateKey = 'welcome';
            $accountKey = 'primary';
            $inboxKey = 'support';
            $expectedResult = [
                'key' => 'welcome',
                'content' => 'Welcome {{name}}!',
                'variables' => ['name']
            ];

            $this->templateServiceMock
                ->shouldReceive('getTemplate')
                ->with($templateKey, $accountKey, $inboxKey)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->getTemplate($templateKey, $accountKey, $inboxKey);

            expect($result)->toBe($expectedResult);
        });

        it('can process a template', function () {
            $templateKey = 'welcome';
            $variables = ['name' => 'John'];
            $accountKey = 'primary';
            $inboxKey = 'support';
            $expectedResult = [
                'processed_content' => 'Welcome John!',
                'character_count' => 13
            ];

            $this->templateServiceMock
                ->shouldReceive('processTemplate')
                ->with($templateKey, $variables, $accountKey, $inboxKey)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->processTemplate($templateKey, $variables, $accountKey, $inboxKey);

            expect($result)->toBe($expectedResult);
        });

        it('can validate a template', function () {
            $templateKey = 'welcome';
            $variables = ['name' => 'John'];
            $accountKey = 'primary';
            $inboxKey = 'support';
            $expectedResult = [
                'valid' => true,
                'missing_variables' => [],
                'extra_variables' => []
            ];

            $this->templateServiceMock
                ->shouldReceive('validateTemplate')
                ->with($templateKey, $variables, $accountKey, $inboxKey)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->validateTemplate($templateKey, $variables, $accountKey, $inboxKey);

            expect($result)->toBe($expectedResult);
        });
    });

    describe('Rate Limiting', function () {
        it('can check rate limits', function () {
            $accountKey = 'primary';
            $inboxKey = 'support';
            $routingInfo = ['account_key' => $accountKey, 'inbox_key' => $inboxKey];

            $this->inboxManagerMock
                ->shouldReceive('getRoutingInfo')
                ->once()
                ->andReturn($routingInfo);

            $this->rateLimitServiceMock
                ->shouldReceive('checkLimit')
                ->with($accountKey, $inboxKey)
                ->once()
                ->andReturn(true);

            $result = $this->chatwoot->checkRateLimit($accountKey, $inboxKey);

            expect($result)->toBeTrue();
        });

        it('can check rate limits with current context', function () {
            $routingInfo = ['account_key' => 'primary', 'inbox_key' => 'support'];

            $this->inboxManagerMock
                ->shouldReceive('getRoutingInfo')
                ->once()
                ->andReturn($routingInfo);

            $this->rateLimitServiceMock
                ->shouldReceive('checkLimit')
                ->with('primary', 'support')
                ->once()
                ->andReturn(false);

            $result = $this->chatwoot->checkRateLimit();

            expect($result)->toBeFalse();
        });

        it('can get rate limit information', function () {
            $accountKey = 'primary';
            $inboxKey = 'support';
            $routingInfo = ['account_key' => $accountKey, 'inbox_key' => $inboxKey];
            $expectedResult = [
                'limit' => 60,
                'remaining' => 45,
                'reset_at' => '2024-01-01 12:00:00'
            ];

            $this->inboxManagerMock
                ->shouldReceive('getRoutingInfo')
                ->once()
                ->andReturn($routingInfo);

            $this->rateLimitServiceMock
                ->shouldReceive('getRateLimitInfo')
                ->with($accountKey, $inboxKey)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->getRateLimitInfo($accountKey, $inboxKey);

            expect($result)->toBe($expectedResult);
        });
    });

    describe('Webhook Management', function () {
        it('can get webhook statistics', function () {
            $accountId = 'primary';
            $days = 7;
            $expectedResult = [
                'received' => 150,
                'processed' => 148,
                'failed' => 2
            ];

            $this->webhookHandlerMock
                ->shouldReceive('getStatistics')
                ->with($accountId, $days)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->getWebhookStatistics($accountId, $days);

            expect($result)->toBe($expectedResult);
        });
    });

    describe('Static Factory Methods', function () {
        it('can create instance with fluent factory method', function () {
            $this->app->instance(AccountManager::class, $this->accountManagerMock);
            $this->app->instance(InboxManager::class, $this->inboxManagerMock);
            $this->app->instance(MessageService::class, $this->messageServiceMock);
            $this->app->instance(TemplateService::class, $this->templateServiceMock);
            $this->app->instance(RateLimitService::class, $this->rateLimitServiceMock);
            $this->app->instance(WebhookHandler::class, $this->webhookHandlerMock);

            $this->accountManagerMock
                ->shouldReceive('account')
                ->with('primary')
                ->once();

            $this->inboxManagerMock
                ->shouldReceive('inbox')
                ->with('support')
                ->once();

            $result = LaravelChatwoot::for('primary', 'support');

            expect($result)->toBeInstanceOf(LaravelChatwoot::class);
        });

        it('can create instance with only account key', function () {
            $this->app->instance(AccountManager::class, $this->accountManagerMock);
            $this->app->instance(InboxManager::class, $this->inboxManagerMock);
            $this->app->instance(MessageService::class, $this->messageServiceMock);
            $this->app->instance(TemplateService::class, $this->templateServiceMock);
            $this->app->instance(RateLimitService::class, $this->rateLimitServiceMock);
            $this->app->instance(WebhookHandler::class, $this->webhookHandlerMock);

            $this->accountManagerMock
                ->shouldReceive('account')
                ->with('primary')
                ->once();

            $result = LaravelChatwoot::for('primary');

            expect($result)->toBeInstanceOf(LaravelChatwoot::class);
        });
    });

    describe('Quick Send Methods', function () {
        it('can send a quick message', function () {
            $content = 'Hello World';
            $contactData = ['phone' => '+1234567890'];
            $options = ['priority' => 'high'];
            $expectedMessage = [
                'content' => $content,
                'contact' => $contactData,
                'priority' => 'high'
            ];
            $expectedResult = ['id' => 125, 'status' => 'sent'];

            $this->messageServiceMock
                ->shouldReceive('sendMessage')
                ->with($expectedMessage)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->send($content, $contactData, $options);

            expect($result)->toBe($expectedResult);
        });

        it('can send a quick template message', function () {
            $templateKey = 'welcome';
            $variables = ['name' => 'John'];
            $contactData = ['email' => 'john@example.com'];
            $options = ['priority' => 'normal'];
            $expectedOptions = [
                'contact' => $contactData,
                'priority' => 'normal'
            ];
            $expectedResult = ['id' => 126, 'status' => 'sent'];

            $this->messageServiceMock
                ->shouldReceive('sendTemplate')
                ->with($templateKey, $variables, $expectedOptions)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot->template($templateKey, $variables, $contactData, $options);

            expect($result)->toBe($expectedResult);
        });
    });

    describe('Method Chaining', function () {
        it('supports fluent method chaining', function () {
            $this->accountManagerMock
                ->shouldReceive('account')
                ->with('primary')
                ->once();

            $this->inboxManagerMock
                ->shouldReceive('inbox')
                ->with('support')
                ->once();

            $messageData = ['content' => 'Test message'];
            $expectedResult = ['id' => 127, 'status' => 'sent'];

            $this->messageServiceMock
                ->shouldReceive('sendMessage')
                ->with($messageData)
                ->once()
                ->andReturn($expectedResult);

            $result = $this->chatwoot
                ->account('primary')
                ->inbox('support')
                ->sendMessage($messageData);

            expect($result)->toBe($expectedResult);
        });
    });
});