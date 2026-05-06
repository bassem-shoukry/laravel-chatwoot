<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\Webhooks\HmacSignatureVerifier;

it('verifies a correct signature', function (): void {
    $payload = '{"event":"message_created"}';
    $secret = 'whsec_test';
    $sig = hash_hmac('sha256', $payload, $secret);

    expect((new HmacSignatureVerifier)->verify($payload, $sig, $secret))->toBeTrue();
});

it('accepts the sha256= prefix', function (): void {
    $payload = '{"event":"x"}';
    $secret = 'whsec_test';
    $sig = 'sha256=' . hash_hmac('sha256', $payload, $secret);

    expect((new HmacSignatureVerifier)->verify($payload, $sig, $secret))->toBeTrue();
});

it('rejects a wrong signature', function (): void {
    expect((new HmacSignatureVerifier)->verify('{"event":"x"}', 'deadbeef', 'whsec_test'))->toBeFalse();
});

it('rejects null and empty signature', function (): void {
    $verifier = new HmacSignatureVerifier;
    expect($verifier->verify('payload', null, 'secret'))->toBeFalse();
    expect($verifier->verify('payload', '', 'secret'))->toBeFalse();
});

it('rejects empty secret', function (): void {
    expect((new HmacSignatureVerifier)->verify('payload', 'sig', ''))->toBeFalse();
});
