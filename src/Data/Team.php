<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data;

use BassamShoukry\LaravelChatwoot\Data\Concerns\InteractsWithArrays;

final readonly class Team
{
    use InteractsWithArrays;

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
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
            description: self::string($data, 'description'),
            raw: $data,
        );
    }
}
