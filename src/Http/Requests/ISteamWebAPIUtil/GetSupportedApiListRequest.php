<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil;

use Fkrzski\SteamApiSdk\Contracts\SendsOptionalApiKey;
use Fkrzski\SteamApiSdk\Dto\WebApiInterface;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

final class GetSupportedApiListRequest extends Request implements SendsOptionalApiKey
{
    #[Override]
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/ISteamWebAPIUtil/GetSupportedAPIList/v1/';
    }

    /**
     * A rejected key answers 403 with an empty JSON list and no `key=` in the body,
     * which the connector would otherwise take for a profile that is not public.
     */
    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        if ($response->status() !== 403) {
            return null;
        }

        return InvalidApiKeyException::rejected($response);
    }

    /**
     * @return list<WebApiInterface>
     */
    public function createDtoFromResponse(Response $response): array
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
