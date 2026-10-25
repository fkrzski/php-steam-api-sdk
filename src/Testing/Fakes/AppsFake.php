<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Testing\Fakes;

use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetSdrConfigRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\GetServersAtAddressRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamApps\UpToDateCheckRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\AppVersionCheckFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\GameServerFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamApps\SdrConfigFactory;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Faking\MockResponse;

/**
 * The ISteamApps endpoints, faked the way `$connector->apps()` reaches them.
 */
final readonly class AppsFake
{
    public function __construct(
        private SteamFake $fake,
    ) {}

    public function upToDateCheck(AppVersionCheckFactory $check): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'success' => true,
                ...$check->toArray(),
            ],
        ]), UpToDateCheckRequest::class);

        return $this->fake;
    }

    /**
     * `UpToDateCheck` answers 200 with `success: false` alike for an app ID Steam
     * does not know and for an app running no versioned servers, raising
     * `AppVersionUnavailableException` either way.
     */
    public function upToDateCheckUnavailable(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'success' => false,
                'error' => "Couldn't get app info for the app specified.",
            ],
        ]), UpToDateCheckRequest::class);

        return $this->fake;
    }

    /**
     * An address with no servers on it still succeeds, and Steam says so in a
     * `message` it sends only then.
     */
    public function serversAtAddress(GameServerFactory ...$servers): SteamFake
    {
        $payload = [
            'success' => true,
            'servers' => array_map(
                static fn (GameServerFactory $server): array => $server->toArray(),
                $servers,
            ),
        ];

        if ($servers === []) {
            $payload['message'] = 'No servers found at that address';
        }

        $this->fake->addResponse(MockResponse::make([
            'response' => $payload,
        ]), GetServersAtAddressRequest::class);

        return $this->fake;
    }

    /**
     * An address `GetServersAtAddress` rejects, raising `InvalidServerAddressException`.
     * The request tells it apart from {@see self::serversAtAddressRefused()} by the
     * `'addr' param` in the message alone.
     */
    public function serversAtAddressInvalid(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'success' => false,
                'message' => "'addr' param should specify a valid IPv4 or IPv4:queryport",
            ],
        ]), GetServersAtAddressRequest::class);

        return $this->fake;
    }

    /**
     * Raised as the root `SteamApiException`, so only the message names this failure.
     */
    public function serversAtAddressRefused(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'response' => [
                'success' => false,
                'message' => "Please don't call this API more often than once per minute for a given IP.",
            ],
        ]), GetServersAtAddressRequest::class);

        return $this->fake;
    }

    /**
     * The config sits at the top level, without the `response` wrapper, beside
     * the `success` flag Steam sends with it.
     */
    public function sdrConfig(SdrConfigFactory $config): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            ...$config->toArray(),
            'success' => true,
        ]), GetSdrConfigRequest::class);

        return $this->fake;
    }

    /**
     * An app ID `GetSDRConfig` does not know, the third shape of it after
     * {@see StatsFake::currentPlayersAppNotFound()} and {@see StatsFake::schemaAppNotFound()}.
     * The request raises `AppNotFoundException` before the connector can retry the 500.
     */
    public function sdrConfigAppNotFound(): SteamFake
    {
        $this->fake->addResponse(MockResponse::make([
            'success' => false,
            'message' => 'Failed to get appinfo',
        ], 500), GetSdrConfigRequest::class);

        return $this->fake;
    }
}
