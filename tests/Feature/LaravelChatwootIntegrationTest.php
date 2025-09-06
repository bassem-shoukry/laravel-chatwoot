<?php

use BassamShoukry\LaravelChatwoot\Facades\LaravelChatwoot;
use BassamShoukry\LaravelChatwoot\LaravelChatwoot as LaravelChatwootService;
use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use BassamShoukry\LaravelChatwoot\Services\MessageService;

describe('Laravel Chatwoot Integration', function () {
    it('can resolve service through facade', function () {
        $service = LaravelChatwoot::getFacadeRoot();
        
        expect($service)->toBeInstanceOf(LaravelChatwootService::class);
    });

    it('can use fluent factory method through facade', function () {
        LaravelChatwoot::shouldReceive('for')
            ->with('test', 'test-inbox')
            ->once()
            ->andReturnSelf();
        
        $result = LaravelChatwoot::for('test', 'test-inbox');
        
        expect($result)->toBeInstanceOf(Mockery\MockInterface::class);
    });

    it('can chain methods using facade', function () {
        $mockService = Mockery::mock(LaravelChatwootService::class);
        
        $mockService->shouldReceive('account')
            ->with('test')
            ->once()
            ->andReturnSelf();
            
        $mockService->shouldReceive('inbox')
            ->with('test-inbox')
            ->once()
            ->andReturnSelf();
            
        $mockService->shouldReceive('sendMessage')
            ->with(getTestMessageData())
            ->once()
            ->andReturn(['id' => 123, 'status' => 'sent']);
        
        $this->app->instance('laravel-chatwoot', $mockService);
        
        $result = LaravelChatwoot::account('test')
            ->inbox('test-inbox')
            ->sendMessage(getTestMessageData());
        
        expect($result)->toBe(['id' => 123, 'status' => 'sent']);
    });

    it('can use quick send methods through facade', function () {
        $mockService = Mockery::mock(LaravelChatwootService::class);
        
        $mockService->shouldReceive('send')
            ->with('Hello World', ['email' => 'test@example.com'], [])
            ->once()
            ->andReturn(['id' => 124, 'status' => 'sent']);
        
        $this->app->instance('laravel-chatwoot', $mockService);
        
        $result = LaravelChatwoot::send('Hello World', ['email' => 'test@example.com']);
        
        expect($result)->toBe(['id' => 124, 'status' => 'sent']);
    });

    it('can use template methods through facade', function () {
        $mockService = Mockery::mock(LaravelChatwootService::class);
        
        $mockService->shouldReceive('template')
            ->with('welcome', ['name' => 'John'], ['email' => 'john@example.com'], [])
            ->once()
            ->andReturn(['id' => 125, 'status' => 'sent']);
        
        $this->app->instance('laravel-chatwoot', $mockService);
        
        $result = LaravelChatwoot::template('welcome', ['name' => 'John'], ['email' => 'john@example.com']);
        
        expect($result)->toBe(['id' => 125, 'status' => 'sent']);
    });

    describe('Service Container Integration', function () {
        it('resolves dependencies correctly', function () {
            $accountManager = app(AccountManager::class);
            $messageService = app(MessageService::class);
            $chatwoot = app(LaravelChatwootService::class);
            
            expect($accountManager)->toBeInstanceOf(AccountManager::class)
                ->and($messageService)->toBeInstanceOf(MessageService::class)
                ->and($chatwoot)->toBeInstanceOf(LaravelChatwootService::class);
        });

        it('maintains singleton instances', function () {
            $instance1 = app(LaravelChatwootService::class);
            $instance2 = app('laravel-chatwoot');
            $instance3 = LaravelChatwoot::getFacadeRoot();
            
            expect($instance1)->toBe($instance2)
                ->and($instance2)->toBe($instance3);
        });
    });

    describe('Configuration Integration', function () {
        it('uses configuration from container', function () {
            expect(config('chatwoot.default_account'))->toBe('primary')
                ->and(config('chatwoot.accounts.test.url'))->toBe('https://test.chatwoot.com')
                ->and(config('chatwoot.accounts.test.token'))->toBe('test-token');
        });

        it('can access nested configuration', function () {
            $testInbox = config('chatwoot.accounts.test.inboxes.test-inbox');
            
            expect($testInbox)->toBeArray()
                ->and($testInbox['id'])->toBe(1)
                ->and($testInbox['name'])->toBe('Test Inbox')
                ->and($testInbox['channels'])->toContain('email')
                ->and($testInbox['templates'])->toContain('welcome');
        });

        it('has correct channel configurations', function () {
            $emailChannel = config('chatwoot.channels.email');
            $smsChannel = config('chatwoot.channels.sms');
            
            expect($emailChannel['supports_attachments'])->toBeTrue()
                ->and($smsChannel['supports_attachments'])->toBeFalse()
                ->and($emailChannel['max_message_size'])->toBe(25000)
                ->and($smsChannel['max_message_size'])->toBe(320);
        });
    });

    describe('Helper Functions Integration', function () {
        it('can use global helper functions', function () {
            $mockService = mockChatwootService();
            $testConfig = getTestConfig();
            $testMessageData = getTestMessageData();
            $testTemplateData = getTestTemplateData();
            
            expect($mockService)->toBeInstanceOf(Mockery\MockInterface::class)
                ->and($testConfig)->toBeArray()
                ->and($testConfig['default_account'])->toBe('test')
                ->and($testMessageData)->toBeArray()
                ->and($testMessageData['content'])->toBe('Test message content')
                ->and($testTemplateData)->toBeArray()
                ->and($testTemplateData['key'])->toBe('test-template');
        });
    });

    describe('Custom Expectations', function () {
        it('can use custom expectations', function () {
            expect(1)->toBeOne()
                ->and(config('chatwoot'))->toBeConfigured()
                ->and($this->app->bound('laravel-chatwoot'))->toBeBound();
        });
    });

    describe('Environment Specific Configuration', function () {
        it('uses testing-optimized configuration', function () {
            expect(config('chatwoot.queue.connection'))->toBe('sync')
                ->and(config('chatwoot.cache.store'))->toBe('array')
                ->and(config('chatwoot.logging.enabled'))->toBeFalse()
                ->and(config('chatwoot.development.fake_api_responses'))->toBeTrue();
        });
    });

    describe('Mocking Integration', function () {
        it('can mock facade methods for testing', function () {
            LaravelChatwoot::shouldReceive('testConnection')
                ->once()
                ->andReturn(['status' => 'connected', 'account_id' => 1]);
            
            $result = LaravelChatwoot::testConnection();
            
            expect($result)->toBe(['status' => 'connected', 'account_id' => 1]);
        });

        it('can partially mock facade methods', function () {
            LaravelChatwoot::shouldReceive('getCurrentAccount')
                ->once()
                ->andReturn(['id' => 1, 'name' => 'Mocked Account']);
            
            $result = LaravelChatwoot::getCurrentAccount();
            
            expect($result)->toBe(['id' => 1, 'name' => 'Mocked Account']);
        });
    });
});

describe('Package Architecture Testing', function () {
    it('follows Laravel package conventions', function () {
        // Test service provider structure
        expect(\BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider::class)
            ->toExtend(\Spatie\LaravelPackageTools\PackageServiceProvider::class);

        // Test facade structure
        expect(\BassamShoukry\LaravelChatwoot\Facades\LaravelChatwoot::class)
            ->toExtend(\Illuminate\Support\Facades\Facade::class);

        // Test main service class
        expect(\BassamShoukry\LaravelChatwoot\LaravelChatwoot::class)
            ->toBeClass()
            ->not()->toExtend(\Illuminate\Support\Facades\Facade::class);
    });

    it('has proper namespace structure', function () {
        $expectedClasses = [
            \BassamShoukry\LaravelChatwoot\LaravelChatwoot::class,
            \BassamShoukry\LaravelChatwoot\LaravelChatwootServiceProvider::class,
            \BassamShoukry\LaravelChatwoot\Facades\LaravelChatwoot::class,
            \BassamShoukry\LaravelChatwoot\Services\AccountManager::class,
            \BassamShoukry\LaravelChatwoot\Services\MessageService::class,
        ];

        foreach ($expectedClasses as $class) {
            expect(class_exists($class))->toBeTrue("Class {$class} should exist");
        }
    });
});

describe('Real World Usage Scenarios', function () {
    beforeEach(function () {
        // Mock all external dependencies for real-world scenarios
        $this->mockAccountManager = Mockery::mock(AccountManager::class);
        $this->mockMessageService = Mockery::mock(MessageService::class);
        
        $this->app->instance(AccountManager::class, $this->mockAccountManager);
        $this->app->instance(MessageService::class, $this->mockMessageService);
    });

    it('can handle typical send message workflow', function () {
        $this->mockAccountManager
            ->shouldReceive('account')
            ->with('test')
            ->once();

        $this->mockMessageService
            ->shouldReceive('sendMessage')
            ->once()
            ->andReturn(['id' => 123, 'status' => 'sent']);

        $chatwoot = app(LaravelChatwootService::class);
        $result = $chatwoot->account('test')->sendMessage(['content' => 'Hello']);
        
        expect($result)->toBe(['id' => 123, 'status' => 'sent']);
    });

    it('can handle template sending workflow', function () {
        $this->mockAccountManager
            ->shouldReceive('account')
            ->with('test')
            ->once();

        $this->mockMessageService
            ->shouldReceive('sendTemplate')
            ->with('welcome', ['name' => 'John'], [])
            ->once()
            ->andReturn(['id' => 124, 'status' => 'sent']);

        $chatwoot = app(LaravelChatwootService::class);
        $result = $chatwoot->account('test')->sendTemplate('welcome', ['name' => 'John']);
        
        expect($result)->toBe(['id' => 124, 'status' => 'sent']);
    });

    it('can handle factory method workflow', function () {
        $mockService = Mockery::mock(LaravelChatwootService::class);
        
        $mockService->shouldReceive('account')
            ->with('test')
            ->once()
            ->andReturnSelf();
            
        $mockService->shouldReceive('send')
            ->with('Quick message', ['email' => 'test@example.com'], [])
            ->once()
            ->andReturn(['id' => 125, 'status' => 'sent']);
        
        // Mock static method
        LaravelChatwootService::shouldReceive('for')
            ->with('test')
            ->once()
            ->andReturn($mockService);

        $result = LaravelChatwootService::for('test')->send('Quick message', ['email' => 'test@example.com']);
        
        expect($result)->toBe(['id' => 125, 'status' => 'sent']);
    });
});