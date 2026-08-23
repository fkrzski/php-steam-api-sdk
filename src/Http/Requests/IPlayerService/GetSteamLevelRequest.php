<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\IPlayerService;

use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

final class GetSteamLevelRequest extends Request
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

    public function createDtoFromResponse(Response $response): int
    {
        /** @var array{response?: array{player_level?: int}} $body */
        $body = $response->json();

        // Level 0 is a real level, so only the missing key marks a withheld profile.
        if (! isset($body['response']['player_level'])) {
            throw ProfileNotPublicException::forSteamId($this->steamId, $response);
        }

        return $body['response']['player_level'];
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return [
            'steamid' => $this->steamId->value,
        ];
    }
}
