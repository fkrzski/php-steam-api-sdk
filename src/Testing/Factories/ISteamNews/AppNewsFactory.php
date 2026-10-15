<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews;

use Fkrzski\SteamApiSdk\Dto\AppNews;

/**
 * Builds the `appnews` payload Steam returns for `GetNewsForApp`.
 */
final readonly class AppNewsFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'appid' => 440,
            'newsitems' => [NewsItemFactory::new()->toArray()],
            'count' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    /**
     * Each item carries its own app ID, which for a DLC is the base game's.
     */
    public function appId(int $appId): self
    {
        return $this->state(['appid' => $appId]);
    }

    /**
     * Syncs `count` too, so a test that wants the two apart calls
     * {@see self::total()} after this.
     */
    public function items(NewsItemFactory ...$items): self
    {
        return $this->state([
            'newsitems' => array_map(
                static fn (NewsItemFactory $item): array => $item->toArray(),
                $items,
            ),
            'count' => count($items),
        ]);
    }

    /**
     * Steam counts every post the filter matches, while `count` on the request
     * caps how many it lists, so the total can outrun the payload — page one of many.
     */
    public function total(int $total): self
    {
        return $this->state(['count' => $total]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): AppNews
    {
        /**
         * @var array{
         *     appid: int,
         *     newsitems: list<array{
         *         gid: string,
         *         title: string,
         *         url: string,
         *         is_external_url: bool,
         *         author: string,
         *         contents: string,
         *         feedlabel: string,
         *         date: int,
         *         feedname: string,
         *         feed_type: int,
         *         appid: int,
         *         tags?: list<string>,
         *     }>,
         *     count: int,
         * } $payload
         */
        $payload = $this->attributes;

        return AppNews::fromArray($payload);
    }
}
