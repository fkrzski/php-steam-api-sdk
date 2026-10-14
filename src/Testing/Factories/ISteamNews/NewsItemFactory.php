<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews;

use DateTimeInterface;
use Fkrzski\SteamApiSdk\Dto\NewsItem;

/**
 * Builds the payload Steam returns for one entry of `newsitems` on `GetNewsForApp`.
 *
 * Defaults describe a press post on TF2, linked through Steam's external-post redirect.
 */
final readonly class NewsItemFactory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    public static function new(): self
    {
        return new self([
            'gid' => '1838407329261909',
            'title' => 'Valve is still working on the Mann vs Machine update',
            'url' => 'https://steamstore-a.akamaihd.net/news/externalpost/PC Gamer/1838407329261909',
            'is_external_url' => true,
            'author' => 'Rick Lane',
            'contents' => '<p>Valve says the Mann vs Machine update it announced last year is still in the works.</p>',
            'feedlabel' => 'PC Gamer',
            'date' => 1784381770,
            'feedname' => 'PC Gamer',
            'feed_type' => 0,
            'appid' => 440,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function state(array $overrides): self
    {
        return new self([...$this->attributes, ...$overrides]);
    }

    public function id(string $id): self
    {
        return $this->state(['gid' => $id]);
    }

    public function title(string $title): self
    {
        return $this->state(['title' => $title]);
    }

    public function appId(int $appId): self
    {
        return $this->state(['appid' => $appId]);
    }

    public function publishedAt(DateTimeInterface $publishedAt): self
    {
        return $this->state(['date' => $publishedAt->getTimestamp()]);
    }

    /**
     * Steam marks an announcement with `feed_type` 1, which only its own
     * announcements feed carries.
     */
    public function communityAnnouncement(): self
    {
        return $this->state([
            'feedlabel' => 'Community Announcements',
            'feedname' => 'steam_community_announcements',
            'feed_type' => 1,
        ]);
    }

    /**
     * A post hosted on the Steam store rather than reached through the
     * external-post redirect every other feed links to.
     */
    public function internalUrl(string $url = 'https://store.steampowered.com/news/285564/'): self
    {
        return $this->state([
            'url' => $url,
            'is_external_url' => false,
        ]);
    }

    /**
     * A post without a byline, which Steam sends as an empty string rather than
     * omitting.
     */
    public function withoutAuthor(): self
    {
        return $this->state(['author' => '']);
    }

    /**
     * Steam drops the `tags` key entirely from an untagged post, so it is only
     * here once this sets it.
     */
    public function tags(string ...$tags): self
    {
        return $this->state(['tags' => $tags]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function make(): NewsItem
    {
        /**
         * @var array{
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
         * } $payload
         */
        $payload = $this->attributes;

        return NewsItem::fromArray($payload);
    }
}
