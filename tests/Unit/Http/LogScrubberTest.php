<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\Http\LogScrubber;

it('redacts sensitive headers', function (): void {
    $headers = LogScrubber::headers([
        'Authorization'    => 'Bearer xyz',
        'api_access_token' => ['secret'],
        'Accept'           => 'application/json',
    ]);

    expect($headers['Authorization'])->toBe('***REDACTED***')
        ->and($headers['api_access_token'])->toBe('***REDACTED***')
        ->and($headers['Accept'])->toBe('application/json');
});

it('redacts sensitive body keys recursively', function (): void {
    $body = LogScrubber::body([
        'name'   => 'Ada',
        'token'  => 'tok-1234',
        'nested' => [
            'password' => 'hunter2',
            'safe'     => 'ok',
        ],
    ]);

    expect($body['token'])->toBe('***REDACTED***')
        ->and($body['nested']['password'])->toBe('***REDACTED***')
        ->and($body['nested']['safe'])->toBe('ok');
});
