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
use Throwable;

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

    /**
     * Steam answers 200 whether it rejects the address or refuses to look it up, and only
     * the message tells the two apart.
     */
    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        if ($this->hasRequestFailed($response) !== true) {
            return null;
        }

        /** @var array{response: array{success: false, message: string}} $body */
        $body = $response->json();
        $message = $body['response']['message'];

        return str_contains($message, "'addr' param")
            ? InvalidServerAddressException::forAddress($this->address, $response)
            : SteamApiException::fromUnsuccessfulResponse($response, $message);
    }

    /**
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
         * }} $body
         */
        $body = $response->json();

        return array_map(GameServer::fromArray(...), $body['response']['servers']);
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
