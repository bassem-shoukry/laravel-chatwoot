<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data;

use BassamShoukry\LaravelChatwoot\Data\Concerns\InteractsWithArrays;

final readonly class Label
{
    use InteractsWithArrays;

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public ?string $color,
        public array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            title: (string) ($data['title'] ?? ''),
            description: self::string($data, 'description'),
            color: self::string($data, 'color'),
            raw: $data,
        );
    }
}
