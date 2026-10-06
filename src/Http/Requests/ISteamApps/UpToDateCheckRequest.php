<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamApps;

use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Dto\AppVersionCheck;
use Fkrzski\SteamApiSdk\Exceptions\AppVersionUnavailableException;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

final class UpToDateCheckRequest extends Request implements SendsNoApiKey
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

    /**
     * Steam answers 200 with `success: false` alike for an app ID it does not know and for
     * an app that runs no versioned servers, so the failure can only be read from the body.
     */
    #[Override]
    public function hasRequestFailed(Response $response): ?bool
    {
        // No opinion off a 200: false would overrule Saloon's own 4xx and 5xx check.
        if ($response->status() !== 200) {
            return null;
        }

        /** @var array{response: array{success: bool}} $body */
        $body = $response->json();

        return $body['response']['success'] === false;
    }

    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return $this->hasRequestFailed($response) === true
            ? AppVersionUnavailableException::forAppId($this->appId, $response)
            : null;
    }

    public function createDtoFromResponse(Response $response): AppVersionCheck
    {
        /**
         * @var array{response: array{
         *     success: true,
         *     up_to_date: bool,
         *     version_is_listable: bool,
         *     required_version?: int,
         *     message?: string,
         * }} $body
         */
        $body = $response->json();

        return AppVersionCheck::fromArray($body['response']);
    }

    /**
     * @return array<string, int>
     */
    protected function defaultQuery(): array
    {
        return [
            'appid' => $this->appId,
            'version' => $this->version,
        ];
    }
}
