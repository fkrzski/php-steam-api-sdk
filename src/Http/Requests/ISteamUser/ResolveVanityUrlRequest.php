<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUser;

use Fkrzski\SteamApiSdk\Exceptions\SteamUserNotFoundException;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

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

    #[Override]
    public function hasRequestFailed(Response $response): ?bool
    {
        // No opinion off a 200: false would overrule Saloon's own 4xx and 5xx check.
        if ($response->status() !== 200) {
            return null;
        }

        /** @var array{response?: array{success?: int, steamid?: string}} $body */
        $body = $response->json();
        $payload = $body['response'] ?? [];

        return ($payload['success'] ?? null) !== 1 || ! isset($payload['steamid']);
    }

    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return $this->hasRequestFailed($response) === true
            ? SteamUserNotFoundException::forVanity($this->vanityName, $response)
            : null;
    }

    public function createDtoFromResponse(Response $response): SteamId
    {
        /** @var array{response: array{success: 1, steamid: string}} $body */
        $body = $response->json();

        return SteamId::fromSteamId64($body['response']['steamid']);
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
