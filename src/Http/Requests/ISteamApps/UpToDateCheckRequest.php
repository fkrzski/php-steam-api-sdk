<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamApps;

use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;
use Fkrzski\SteamApiSdk\Exceptions\AppVersionUnavailableException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<AppVersionCheck>
 */
final class UpToDateCheckRequest extends SteamApiRequest implements SendsNoApiKey
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly int $appId,
        public readonly int $version,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamApps/UpToDateCheck/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['response.success'];
    }

    /**
     * Steam answers 200 with `success: false` alike for an app ID it does not know and for
     * an app that runs no versioned servers, so the failure can only be read from the body.
     */
    protected function read(Response $response): AppVersionCheck|AppVersionUnavailableException
    {
        /**
         * @var array{response: array{success: false}|array{
         *     success: true,
         *     up_to_date: bool,
         *     version_is_listable: bool,
         *     required_version?: int,
         *     message?: string,
         * }} $body
         */
        $body = $response->json();

        if ($body['response']['success'] === false) {
            return AppVersionUnavailableException::forAppId($this->appId, $response);
        }

        return AppVersionCheck::fromArray($body['response']);
    }

    protected function defaultQuery(): array
    {
        return [
            'appid' => $this->appId,
            'version' => $this->version,
        ];
    }
}
