<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil;

use Fkrzski\SteamApiSdk\Contracts\SendsOptionalApiKey;
use Fkrzski\SteamApiSdk\Dto\WebApiInterface;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<list<WebApiInterface>>
 */
final class GetSupportedApiListRequest extends SteamApiRequest implements SendsOptionalApiKey
{
    #[Override]
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/ISteamWebAPIUtil/GetSupportedAPIList/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['apilist.interfaces'];
    }

    /**
     * A rejected key answers 403 with an empty JSON list and no `key=` in the body,
     * which the connector would otherwise take for a profile that is not public.
     */
    #[Override]
    protected function readError(Response $response): ?InvalidApiKeyException
    {
        if ($response->status() !== 403) {
            return null;
        }

        return InvalidApiKeyException::rejected($response);
    }

    /**
     * @return list<WebApiInterface>
     */
    protected function read(Response $response): array
    {
        /**
         * @var array{apilist: array{interfaces: list<array{
         *     name: string,
         *     methods: list<array{
         *         name: string,
         *         version: int,
         *         httpmethod: string,
         *         description?: string,
         *         parameters: list<array{name: string, type: string, optional: bool, description?: string}>,
         *     }>,
         * }>}} $body
         */
        $body = $response->json();

        return array_map(WebApiInterface::fromArray(...), $body['apilist']['interfaces']);
    }
}
