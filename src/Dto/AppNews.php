<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Dto;

final readonly class AppNews
{
    /**
     * @param  list<NewsItem>  $items
     */
    public function __construct(
        public int $appId,
        public array $items,
        public int $total,
    ) {}

    /**
     * @param  array{appid: int, newsitems: list<array{
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
     * }>, count: int}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            appId: $payload['appid'],
            items: array_map(NewsItem::fromArray(...), $payload['newsitems']),
            total: $payload['count'],
        );
    }
}
