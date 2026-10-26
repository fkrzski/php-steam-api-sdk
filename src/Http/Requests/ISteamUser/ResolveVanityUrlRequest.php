<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUser;

use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<SteamId>
 */
final class ResolveVanityUrlRequest extends SteamApiRequest
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly string $vanityName,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamUser/ResolveVanityURL/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['response.success'];
    }

    protected function read(Response $response): SteamId|SteamUserNotFoundException
    {
        /** @var array{response: array{success: int, steamid?: string}} $body */
        $body = $response->json();

        if ($body['response']['success'] !== 1 || ! isset($body['response']['steamid'])) {
            return SteamUserNotFoundException::forVanity($this->vanityName, $response);
        }

        return SteamId::fromSteamId64($body['response']['steamid']);
    }

    protected function defaultQuery(): array
    {
        return [
            'vanityurl' => $this->vanityName,
        ];
    }
}
