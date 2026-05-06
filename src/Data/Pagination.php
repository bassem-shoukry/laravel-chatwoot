<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data;

final readonly class Pagination
{
    public function __construct(
        public int $currentPage,
        public ?int $totalCount,
        public int $perPage,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data, int $perPage = 25): self
    {
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        return new self(
            currentPage: (int) ($meta['current_page'] ?? $data['current_page'] ?? 1),
            totalCount: isset($meta['count']) ? (int) $meta['count'] : (isset($data['total_count']) ? (int) $data['total_count'] : null),
            perPage: $perPage,
        );
    }
}
