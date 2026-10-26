<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamApps;

use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Dto\GameServer;
use Fkrzski\SteamApiSdk\Exceptions\InvalidServerAddressException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<list<GameServer>>
 */
final class GetServersAtAddressRequest extends SteamApiRequest implements SendsNoApiKey
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
     * Steam explains a refusal in `message` and lists the servers only on success.
     */
    public function requiredPaths(Response $response): array
    {
        return ['response.success', $response->json('response.success') === false ? 'response.message' : 'response.servers'];
    }

    /**
     * Steam answers 200 whether it rejects the address or refuses to look it up, and only
     * the message tells the two apart.
     *
     * @return list<GameServer>|SteamApiException
     */
    protected function read(Response $response): array|SteamApiException
    {
        /**
         * @var array{response: array{success: false, message: string}|array{
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
         * }} $body
         */
        $body = $response->json();

        if ($body['response']['success'] === false) {
            $message = $body['response']['message'];

            return str_contains($message, "'addr' param")
                ? InvalidServerAddressException::forAddress($this->address, $response)
                : SteamApiException::fromUnsuccessfulResponse($response, $message);
        }

        return array_map(GameServer::fromArray(...), $body['response']['servers']);
    }

    protected function defaultQuery(): array
    {
        return [
            'addr' => $this->address,
        ];
    }
}
