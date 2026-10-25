<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Fakes;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetServerInfoRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetSupportedApiListRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiInterfaceFactory;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Faking\MockResponse;

/**
 * The ISteamWebAPIUtil endpoints, faked the way `$connector->webApi()` reaches them.
 */
final readonly class WebApiFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    /**
     * `servertimestring` is the same moment in US Pacific time without an offset,
     * which nothing reads; it is here because Steam sends it.
     */
    public function serverInfo(DateTimeInterface $time): SteamFake
    {
        $pacific = DateTimeImmutable::createFromInterface($time)->setTimezone(new DateTimeZone('America/Los_Angeles'));

        $this->fake->addResponse(MockResponse::make([
            'servertime' => $time->getTimestamp(),
            'servertimestring' => sprintf('%s %2d %s', $pacific->format('D M'), $pacific->format('j'), $pacific->format('H:i:s Y')),
        ]), GetServerInfoRequest::class);

        return $this->fake;
    }

    public function supportedApiList(WebApiInterfaceFactory ...$interfaces): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'apilist' => [
                'interfaces' => array_map(
                    static fn (WebApiInterfaceFactory $interface): array => $interface->toArray(),
                    $interfaces,
                ),
            ],
        ]), GetSupportedApiListRequest::class);

        return $this->fake;
    }

    /**
     * A key `GetSupportedAPIList` rejects, answered with a 403 and an empty list rather
     * than the `key=` page {@see SteamFake::invalidApiKey()} fakes. The request raises
     * `InvalidApiKeyException` itself before the connector can read it as a hidden profile.
     */
    public function supportedApiListInvalidKey(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make(['apilist' => ['interfaces' => []]], 403), GetSupportedApiListRequest::class);

        return $this->fake;
    }
}
