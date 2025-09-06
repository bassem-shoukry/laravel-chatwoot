<?php

use BassamShoukry\LaravelChatwoot\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Traits
|--------------------------------------------------------------------------
|
| Below you may define global traits that should be applied to all tests.
| These traits will be used in all tests unless explicitly overridden.
|
*/

// Apply RefreshDatabase to Feature tests that need database interactions
uses(RefreshDatabase::class)->in('Feature');

// Apply WithFaker to tests that need fake data
uses(WithFaker::class)->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

// Custom expectation for Laravel package testing
expect()->extend('toBeConfigured', function () {
    return $this->not()->toBeNull();
});

// Custom expectation for service container bindings
expect()->extend('toBeBound', function () {
    return $this->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Helper function to create a mock Chatwoot service
 */
function mockChatwootService(): Mockery\MockInterface
{
    return Mockery::mock(\BassamShoukry\LaravelChatwoot\LaravelChatwoot::class);
}

/**
 * Helper function to get a test configuration array
 */
function getTestConfig(): array
{
    return [
        'default_account' => 'test',
        'accounts' => [
            'test' => [
                'url' => 'https://test.chatwoot.com',
                'token' => 'test-token',
                'default_inbox' => 'test-inbox',
                'inboxes' => [
                    'test-inbox' => [
                        'id' => 1,
                        'name' => 'Test Inbox',
                        'channels' => ['email'],
                        'templates' => ['test-template'],
                        'rate_limits' => [
                            'per_minute' => 10,
                            'per_hour' => 100,
                            'per_day' => 1000,
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Helper function to create test message data
 */
function getTestMessageData(): array
{
    return [
        'content' => 'Test message content',
        'contact' => [
            'name' => 'Test Contact',
            'email' => 'test@example.com',
            'phone_number' => '+1234567890',
        ],
        'priority' => 'normal',
    ];
}

/**
 * Helper function to create test template data
 */
function getTestTemplateData(): array
{
    return [
        'key' => 'test-template',
        'name' => 'Test Template',
        'content' => 'Hello {{name}}, welcome to our service!',
        'variables' => ['name'],
        'channel_restrictions' => ['email', 'sms'],
    ];
}
