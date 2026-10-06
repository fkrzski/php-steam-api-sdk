<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\IPlayerService;

use Fkrzski\SteamApiSdk\Exceptions\ProfileNotPublicException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

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

    /**
     * Level 0 is a real level, so only the missing key marks a withheld profile.
     */
    #[Override]
    public function hasRequestFailed(Response $response): ?bool
    {
        // No opinion off a 200: false would overrule Saloon's own 4xx and 5xx check.
        if ($response->status() !== 200) {
            return null;
        }

        /** @var array{response?: array{player_level?: int}} $body */
        $body = $response->json();

        return ! isset($body['response']['player_level']);
    }

    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return $this->hasRequestFailed($response) === true
            ? ProfileNotPublicException::forSteamId($this->steamId, $response)
            : null;
    }

    public function createDtoFromResponse(Response $response): int
    {
        /** @var array{response: array{player_level: int}} $body */
        $body = $response->json();

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
