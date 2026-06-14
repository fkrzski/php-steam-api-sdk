<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUser;

use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class ResolveVanityUrlRequest extends Request
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

    public function createDtoFromResponse(Response $response): SteamId
    {
        /** @var array{response?: array{success?: int, steamid?: string}} $body */
        $body = $response->json();
        $payload = $body['response'] ?? [];

        if (($payload['success'] ?? null) === 1 && isset($payload['steamid'])) {
            return SteamId::fromSteamId64($payload['steamid']);
        }

        throw SteamUserNotFoundException::forVanity($this->vanityName);
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return [
            'vanityurl' => $this->vanityName,
        ];
    }
}
