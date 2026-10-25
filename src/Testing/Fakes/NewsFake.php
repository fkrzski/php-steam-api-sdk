<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Fakes;

use DateTimeInterface;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\AppNewsFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

/**
 * The ISteamNews endpoint, faked the way `$connector->news()` reaches it.
 */
final readonly class NewsFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    /**
     * `count` is Steam's own total for the filter, so it is independent of how
     * many items the payload lists — see {@see AppNewsFactory::total()}.
     */
    public function appNews(AppNewsFactory $news): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'appnews' => $news->toArray(),
        ]), GetNewsForAppRequest::class);

        return $this->fake;
    }

    /**
     * A whole feed, paged per request the way Steam pages it: up to `count` items, 20
     * when the request sends none, dated on or before `enddate`, under a `count` of how
     * many remain. Pass them newest first with their own `id()`; `feeds`, `tags` and
     * `maxlength` are not applied.
     */
    public function newsFeed(NewsItemFactory ...$items): SteamFake
    {
        $this->fake->addResponse(static function (PendingRequest $pendingRequest) use ($items): MockResponse {
            /** @var GetNewsForAppRequest $request */
            $request = $pendingRequest->getRequest();

            $remaining = array_filter(
                $items,
                static fn (NewsItemFactory $item): bool => ! $request->endDate instanceof DateTimeInterface
                    || $item->make()->publishedAt->getTimestamp() <= $request->endDate->getTimestamp(),
            );

            return MockResponse::make([
                'appnews' => AppNewsFactory::new()
                    ->appId($request->appId)
                    ->items(...array_slice($remaining, 0, $request->count ?? 20))
                    ->total(count($remaining))
                    ->toArray(),
            ]);
        }, GetNewsForAppRequest::class);

        return $this->fake;
    }

    /**
     * `GetNewsForApp` answers 403 with an empty JSON object for an app ID it does
     * not know and for some apps that exist, such as Spacewar, raising
     * `AppNewsUnavailableException` either way. The request claims this status
     * before the connector can read it as a rejected key or a hidden profile.
     */
    public function appNewsUnavailable(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make('{}', 403), GetNewsForAppRequest::class);

        return $this->fake;
    }
}
