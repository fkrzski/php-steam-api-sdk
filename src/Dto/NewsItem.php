<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

use DateTimeImmutable;

final readonly class NewsItem
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public bool $isExternalUrl,
        public ?string $author,
        public string $contents,
        public string $feedLabel,
        public string $feedName,
        public bool $isCommunityAnnouncement,
        public DateTimeImmutable $publishedAt,
        public int $appId,
        public array $tags,
    ) {}

    /**
     * @param  array{
     *     gid: string,
     *     title: string,
     *     url: string,
     *     is_external_url: bool,
     *     author: string,
     *     contents: string,
     *     feedlabel: string,
     *     date: int,
     *     feedname: string,
     *     feed_type: int,
     *     appid: int,
     *     tags?: list<string>,
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            id: $payload['gid'],
            title: $payload['title'],
            url: $payload['url'],
            isExternalUrl: $payload['is_external_url'],
            author: $payload['author'] !== '' ? $payload['author'] : null,
            contents: $payload['contents'],
            feedLabel: $payload['feedlabel'],
            feedName: $payload['feedname'],
            isCommunityAnnouncement: $payload['feed_type'] === 1,
            publishedAt: (new DateTimeImmutable)->setTimestamp($payload['date']),
            appId: $payload['appid'],
            tags: $payload['tags'] ?? [],
        );
    }
}
