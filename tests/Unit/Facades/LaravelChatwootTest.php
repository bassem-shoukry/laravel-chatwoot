<?php

use BassamShoukry\LaravelChatwoot\Facades\LaravelChatwoot;
use BassamShoukry\LaravelChatwoot\LaravelChatwoot as LaravelChatwootService;
use Illuminate\Support\Facades\Facade;

describe('LaravelChatwoot Facade', function () {
    it('extends Laravel Facade class', function () {
        expect(LaravelChatwoot::class)->toExtend(Facade::class);
    });

    it('returns correct facade accessor', function () {
        $reflection = new \ReflectionClass(LaravelChatwoot::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);
        $accessor = $method->invoke(new LaravelChatwoot);

        expect($accessor)->toBe('laravel-chatwoot');
    });

    it('can access facade methods', function () {
        // Mock the underlying service
        $mockService = Mockery::mock(LaravelChatwootService::class);

        $mockService->shouldReceive('account')
            ->with('primary')
            ->once()
            ->andReturnSelf();

        $this->app->instance(LaravelChatwootService::class, $mockService);

        $result = LaravelChatwoot::account('primary');

        expect($result)->toBe($mockService);
    });

    it('can chain facade method calls', function () {
        $mockService = Mockery::mock(LaravelChatwootService::class);

        $mockService->shouldReceive('account')
            ->with('primary')
            ->once()
            ->andReturnSelf();

        $mockService->shouldReceive('inbox')
            ->with('support')
            ->once()
            ->andReturnSelf();

        $mockService->shouldReceive('sendMessage')
            ->with(['content' => 'test'])
            ->once()
            ->andReturn(['id' => 123]);

        $this->app->instance(LaravelChatwootService::class, $mockService);

        $result = LaravelChatwoot::account('primary')
            ->inbox('support')
            ->sendMessage(['content' => 'test']);

        expect($result)->toBe(['id' => 123]);
    });

    it('can call static factory method through facade', function () {
        $mockService = Mockery::mock(LaravelChatwootService::class);

        $mockService->shouldReceive('for')
            ->with('primary', 'support')
            ->once()
            ->andReturn($mockService);

        $this->app->instance('laravel-chatwoot', $mockService);

        $result = LaravelChatwoot::for('primary', 'support');

        expect($result)->toBe($mockService);
    });

    describe('Facade Method Availability', function () {
        beforeEach(function () {
            $this->mockService = Mockery::mock(LaravelChatwootService::class);
            $this->app->instance('laravel-chatwoot', $this->mockService);
        });

        it('provides account selection method', function () {
            $this->mockService->shouldReceive('account')
                ->with('test-account')
                ->once()
                ->andReturnSelf();

            $result = LaravelChatwoot::account('test-account');

            expect($result)->toBe($this->mockService);
        });

        it('provides inbox selection method', function () {
            $this->mockService->shouldReceive('inbox')
                ->with('test-inbox')
                ->once()
                ->andReturnSelf();

            $result = LaravelChatwoot::inbox('test-inbox');

            expect($result)->toBe($this->mockService);
        });

        it('provides sendMessage method', function () {
            $messageData = ['content' => 'Test message'];
            $expectedResult = ['id' => 123, 'status' => 'sent'];

            $this->mockService->shouldReceive('sendMessage')
                ->with($messageData)
                ->once()
                ->andReturn($expectedResult);

            $result = LaravelChatwoot::sendMessage($messageData);

            expect($result)->toBe($expectedResult);
        });

        it('provides sendTemplate method', function () {
            $templateKey = 'welcome';
            $variables = ['name' => 'John'];
            $options = ['priority' => 'high'];
            $expectedResult = ['id' => 124, 'status' => 'sent'];

            $this->mockService->shouldReceive('sendTemplate')
                ->with($templateKey, $variables, $options)
                ->once()
                ->andReturn($expectedResult);

            $result = LaravelChatwoot::sendTemplate($templateKey, $variables, $options);

            expect($result)->toBe($expectedResult);
        });

        it('provides sendBulkMessages method', function () {
            $messages = [['content' => 'Bulk message 1']];
            $expectedResult = ['sent' => 1, 'failed' => 0];

            $this->mockService->shouldReceive('sendBulkMessages')
                ->with($messages)
                ->once()
                ->andReturn($expectedResult);

            $result = LaravelChatwoot::sendBulkMessages($messages);

            expect($result)->toBe($expectedResult);
        });

        it('provides queueMessage method', function () {
            $messageData = ['content' => 'Queued message'];
            $delay = 60;
            $expectedJobId = 'job_123';

            $this->mockService->shouldReceive('queueMessage')
                ->with($messageData, $delay)
                ->once()
                ->andReturn($expectedJobId);

            $result = LaravelChatwoot::queueMessage($messageData, $delay);

            expect($result)->toBe($expectedJobId);
        });

        it('provides testConnection method', function () {
            $expectedResult = ['status' => 'connected'];

            $this->mockService->shouldReceive('testConnection')
                ->with('primary')
                ->once()
                ->andReturn($expectedResult);

            $result = LaravelChatwoot::testConnection('primary');

            expect($result)->toBe($expectedResult);
        });

        it('provides getCurrentAccount method', function () {
            $expectedAccount = ['id' => 1, 'name' => 'Test Account'];

            $this->mockService->shouldReceive('getCurrentAccount')
                ->withNoArgs()
                ->once()
                ->andReturn($expectedAccount);

            $result = LaravelChatwoot::getCurrentAccount();

            expect($result)->toBe($expectedAccount);
        });

        it('provides getCurrentInbox method', function () {
            $expectedInbox = ['id' => 1, 'name' => 'Support Inbox'];

            $this->mockService->shouldReceive('getCurrentInbox')
                ->withNoArgs()
                ->once()
                ->andReturn($expectedInbox);

            $result = LaravelChatwoot::getCurrentInbox();

            expect($result)->toBe($expectedInbox);
        });

        it('provides getTemplates method', function () {
            $expectedTemplates = [
                ['key' => 'welcome', 'name' => 'Welcome Message'],
            ];

            $this->mockService->shouldReceive('getTemplates')
                ->with('primary', 'support')
                ->once()
                ->andReturn($expectedTemplates);

            $result = LaravelChatwoot::getTemplates('primary', 'support');

            expect($result)->toBe($expectedTemplates);
        });

        it('provides checkRateLimit method', function () {
            $this->mockService->shouldReceive('checkRateLimit')
                ->with('primary', 'support')
                ->once()
                ->andReturn(true);

            $result = LaravelChatwoot::checkRateLimit('primary', 'support');

            expect($result)->toBeTrue();
        });

        it('provides quick send method', function () {
            $content = 'Quick message';
            $contactData = ['phone' => '+1234567890'];
            $options = ['priority' => 'high'];
            $expectedResult = ['id' => 125, 'status' => 'sent'];

            $this->mockService->shouldReceive('send')
                ->with($content, $contactData, $options)
                ->once()
                ->andReturn($expectedResult);

            $result = LaravelChatwoot::send($content, $contactData, $options);

            expect($result)->toBe($expectedResult);
        });

        it('provides quick template method', function () {
            $templateKey = 'welcome';
            $variables = ['name' => 'John'];
            $contactData = ['email' => 'john@example.com'];
            $options = ['priority' => 'normal'];
            $expectedResult = ['id' => 126, 'status' => 'sent'];

            $this->mockService->shouldReceive('template')
                ->with($templateKey, $variables, $contactData, $options)
                ->once()
                ->andReturn($expectedResult);

            $result = LaravelChatwoot::template($templateKey, $variables, $contactData, $options);

            expect($result)->toBe($expectedResult);
        });
    });

    describe('Facade Integration', function () {
        it('maintains service instance across calls', function () {
            $mockService = Mockery::mock(LaravelChatwootService::class);
            $this->app->instance(LaravelChatwootService::class, $mockService);

            // First call should create and store the instance
            $mockService->shouldReceive('account')
                ->with('primary')
                ->once()
                ->andReturnSelf();

            LaravelChatwoot::account('primary');

            // Second call should use the same instance
            $mockService->shouldReceive('inbox')
                ->with('support')
                ->once()
                ->andReturnSelf();

            LaravelChatwoot::inbox('support');
        });

        it('can be mocked for testing', function () {
            $mockService = Mockery::mock(LaravelChatwootService::class);
            $mockService->shouldReceive('sendMessage')
                ->with(['content' => 'Test'])
                ->once()
                ->andReturn(['id' => 999, 'status' => 'mocked']);

            $this->app->instance('laravel-chatwoot', $mockService);

            $result = LaravelChatwoot::sendMessage(['content' => 'Test']);

            expect($result)->toBe(['id' => 999, 'status' => 'mocked']);
        });

        it('can be partially mocked', function () {
            $mockService = Mockery::mock(LaravelChatwootService::class);
            $mockService->shouldReceive('account')
                ->with('primary')
                ->once()
                ->andReturnSelf()
                ->shouldReceive('getCurrentAccount')
                ->once()
                ->andReturn(['id' => 1, 'name' => 'Mocked Account']);

            $this->app->instance('laravel-chatwoot', $mockService);

            $chatwoot = LaravelChatwoot::account('primary');
            $account = LaravelChatwoot::getCurrentAccount();

            expect($account)->toBe(['id' => 1, 'name' => 'Mocked Account']);
        });
    });
});
