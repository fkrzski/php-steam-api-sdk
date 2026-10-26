<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats;

use Fkrzski\SteamApiSdk\Contracts\HasLanguage;
use Fkrzski\SteamApiSdk\Dto\GameSchema;
use Fkrzski\SteamApiSdk\Enums\Language;
use Fkrzski\SteamApiSdk\Exceptions\AppNotFoundException;
use Fkrzski\SteamApiSdk\Http\Requests\SteamApiRequest;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Response;

/**
 * @extends SteamApiRequest<GameSchema>
 */
final class GetSchemaForGameRequest extends SteamApiRequest implements HasLanguage
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        public readonly int $appId,
        public readonly ?Language $language = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamUserStats/GetSchemaForGame/v2/';
    }

    public function requiredPaths(Response $response): array
    {
        return ['game'];
    }

    /**
     * An app ID Steam does not know answers 400 with an empty JSON object; a missing
     * API key also lands on 400 but as HTML, which the connector claims. An app that
     * merely publishes no schema answers 200, so it stays a DTO rather than a failure.
     */
    #[Override]
    protected function readError(Response $response): ?AppNotFoundException
    {
        if ($response->status() !== 400 || ! json_validate($response->body())) {
            return null;
        }

        return AppNotFoundException::forAppId($this->appId, $response);
    }

    protected function read(Response $response): GameSchema
    {
        /**
         * @var array{game: array{
         *     gameName?: string,
         *     gameVersion?: string,
         *     availableGameStats?: array{
         *         stats?: list<array{name: string, defaultvalue: int|float, displayName: string}>,
         *         achievements?: list<array{
         *             name: string,
         *             displayName: string,
         *             hidden: int,
         *             description?: string,
         *             icon: string,
         *             icongray: string,
         *         }>,
         *     },
         * }} $body
         */
        $body = $response->json();

        return GameSchema::fromArray($body['game']);
    }

    protected function defaultQuery(): array
    {
        $query = [
            'appid' => $this->appId,
        ];

        if ($this->language instanceof Language) {
            $query['l'] = $this->language->value;
        }

        return $query;
    }
}
