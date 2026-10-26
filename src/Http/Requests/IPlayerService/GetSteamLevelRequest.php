<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\IPlayerService;

use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<int>
 */
final class GetSteamLevelRequest extends SteamApiRequest
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly SteamId $steamId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/IPlayerService/GetSteamLevel/v1/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['response'];
    }

    /**
     * Level 0 is a real level, so only the missing key marks a withheld profile.
     */
    protected function read(Response $response): int|ProfileNotPublicException
    {
        /** @var array{response: array{player_level?: int}} $body */
        $body = $response->json();

        if (! isset($body['response']['player_level'])) {
            return ProfileNotPublicException::forSteamId($this->steamId, $response);
        }

        return $body['response']['player_level'];
    }

    protected function defaultQuery(): array
    {
        return [
            'steamid' => $this->steamId->value,
        ];
    }
}
