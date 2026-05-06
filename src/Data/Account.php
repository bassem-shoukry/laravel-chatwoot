<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data;

use BassamShoukry\LaravelChatwoot\Data\Concerns\InteractsWithArrays;

final readonly class Account
{
    use InteractsWithArrays;

    public function __construct(
        public int $id,
        public string $name,
        public ?string $domain = null,
        public ?string $supportEmail = null,
        /** @var array<string, mixed> */
        public array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            domain: self::string($data, 'domain'),
            supportEmail: self::string($data, 'support_email'),
            raw: $data,
        );
    }
}
