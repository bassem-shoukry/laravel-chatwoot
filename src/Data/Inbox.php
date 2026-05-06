<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data;

use BassamShoukry\LaravelChatwoot\Data\Concerns\InteractsWithArrays;
use BassamShoukry\LaravelChatwoot\Enums\ChannelType;

final readonly class Inbox
{
    use InteractsWithArrays;

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public int $id,
        public string $name,
        public ChannelType $channelType,
        public ?string $websiteUrl,
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
            channelType: ChannelType::fromChatwoot((string) ($data['channel_type'] ?? 'api')),
            websiteUrl: self::string($data, 'website_url'),
            raw: $data,
        );
    }
}
