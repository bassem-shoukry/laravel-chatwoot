<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data;

use BassamShoukry\LaravelChatwoot\Data\Concerns\InteractsWithArrays;

final readonly class Attachment
{
    use InteractsWithArrays;

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public int $id,
        public ?string $fileType,
        public ?string $dataUrl,
        public ?string $thumbUrl,
        public ?int $fileSize,
        public array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            fileType: self::string($data, 'file_type'),
            dataUrl: self::string($data, 'data_url'),
            thumbUrl: self::string($data, 'thumb_url'),
            fileSize: self::int($data, 'file_size'),
            raw: $data,
        );
    }
}
