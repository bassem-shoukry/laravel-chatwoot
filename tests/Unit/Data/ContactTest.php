<?php

declare(strict_types=1);

use BassamShoukry\LaravelChatwoot\Data\Contact;

it('locates the source id for an inbox', function (): void {
    $contact = Contact::from([
        'id'              => 11,
        'name'            => 'Ada',
        'contact_inboxes' => [
            ['inbox' => ['id' => 1], 'source_id' => '20100000000'],
            ['inbox' => ['id' => 2], 'source_id' => '20111111111'],
        ],
    ]);

    expect($contact->sourceIdFor(2))->toBe('20111111111')
        ->and($contact->sourceIdFor(99))->toBeNull();
});
