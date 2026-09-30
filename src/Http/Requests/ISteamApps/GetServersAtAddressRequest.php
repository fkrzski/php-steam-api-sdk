<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamApps;

use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Dto\GameServer;
use Fkrzski\SteamApiSdk\Exceptions\InvalidServerAddressException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetServersAtAddressRequest extends Request implements SendsNoApiKey
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly string $address,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamApps/GetServersAtAddress/v1/';
    }

    /**
     * Steam answers 200 whether it rejects the address or refuses to look it up, and only
     * the message tells the two apart.
     *
     * @return list<GameServer>
     */
    public function createDtoFromResponse(Response $response): array
    {
        /**
         * @var array{response: array{
         *     success: true,
         *     servers: list<array{
         *         addr: string,
         *         steamid: string,
         *         appid: int,
         *         gamedir: string,
         *         region: int,
         *         secure: bool,
         *         lan: bool,
         *         gameport: int,
         *         specport: int,
         *     }>,
         *     message?: string,
         * }|array{
         *     success: false,
         *     message: string,
         * }} $body
         */
        $body = $response->json();
        $payload = $body['response'];

        if ($payload['success'] === false) {
            throw str_contains($payload['message'], "'addr' param")
                ? InvalidServerAddressException::forAddress($this->address, $response)
                : SteamApiException::fromUnsuccessfulResponse($response, $payload['message']);
        }

        return array_map(GameServer::fromArray(...), $payload['servers']);
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return [
            'addr' => $this->address,
        ];
    }
}
