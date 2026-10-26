<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamApps;

use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Dto\SdrConfig;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<SdrConfig>
 */
final class GetSdrConfigRequest extends SteamApiRequest implements SendsNoApiKey
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly int $appId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamApps/GetSDRConfig/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['revision', 'pops'];
    }

    /**
     * An app Steam does not know answers 500 with `Failed to get appinfo`, which the
     * connector would otherwise retry as a server error before flattening it.
     */
    #[Override]
    protected function readError(Response $response): ?AppNotFoundException
    {
        if ($response->status() !== 500 || ! str_contains($response->body(), 'Failed to get appinfo')) {
            return null;
        }

        return AppNotFoundException::forAppId($this->appId, $response);
    }

    protected function read(Response $response): SdrConfig
    {
        /**
         * @var array{
         *     revision: int,
         *     pops: array<string, array{
         *         desc: string,
         *         geo: array{int|float, int|float},
         *         aliases?: list<string>,
         *         relays?: list<array{ipv4: string, port_range: array{int, int}}>,
         *     }>,
         * } $body
         */
        $body = $response->json();

        return SdrConfig::fromArray($body);
    }

    protected function defaultQuery(): array
    {
        return [
            'appid' => $this->appId,
        ];
    }
}
