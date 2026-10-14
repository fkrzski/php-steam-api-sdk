<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Fakes;

use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\AppNewsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;

/**
 * The ISteamNews endpoint, faked the way `$connector->news()` reaches it.
 */
final readonly class NewsFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function appNews(AppNewsFactory $news): SteamFake
    {
        $this->fake->addResponse(SteamResponse::appNews($news), GetNewsForAppRequest::class);

        return $this->fake;
    }

    /**
     * Pages the whole feed per request the way Steam does — see {@see SteamResponse::newsFeed()}.
     */
    public function newsFeed(NewsItemFactory ...$items): SteamFake
    {
        $this->fake->addResponse(SteamResponse::newsFeed(...$items), GetNewsForAppRequest::class);

        return $this->fake;
    }

    public function appNewsUnavailable(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::appNewsUnavailable(), GetNewsForAppRequest::class);

        return $this->fake;
    }
}
