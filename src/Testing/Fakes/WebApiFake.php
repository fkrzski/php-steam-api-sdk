<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Fakes;

use DateTimeInterface;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetServerInfoRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetSupportedApiListRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiInterfaceFactory;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;

/**
 * The ISteamWebAPIUtil endpoints, faked the way `$connector->webApi()` reaches them.
 */
final readonly class WebApiFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function serverInfo(DateTimeInterface $time): SteamFake
    {
        $this->fake->addResponse(SteamResponse::serverInfo($time), GetServerInfoRequest::class);

        return $this->fake;
    }

    public function supportedApiList(WebApiInterfaceFactory ...$interfaces): SteamFake
    {
        $this->fake->addResponse(SteamResponse::supportedApiList(...$interfaces), GetSupportedApiListRequest::class);

        return $this->fake;
    }

    public function supportedApiListInvalidKey(): SteamFake
    {
        $this->fake->addResponse(SteamResponse::supportedApiListInvalidKey(), GetSupportedApiListRequest::class);

        return $this->fake;
    }
}
