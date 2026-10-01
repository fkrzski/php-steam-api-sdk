<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Requests\ISteamNews;

use DateTimeInterface;
use Fkrzski\SteamApiSdk\Contracts\SendsNoApiKey;
use Fkrzski\SteamApiSdk\Dto\AppNews;
use Fkrzski\SteamApiSdk\Exceptions\AppNewsUnavailableException;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Throwable;

final class GetNewsForAppRequest extends Request implements SendsNoApiKey
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  list<string>  $feeds
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly int $appId,
        public readonly ?int $count = null,
        public readonly ?int $maxLength = null,
        public readonly ?DateTimeInterface $endDate = null,
        public readonly array $feeds = [],
        public readonly array $tags = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/ISteamNews/GetNewsForApp/v2/';
    }

    /**
     * Steam answers 403 with an empty JSON object for an app ID it does not know and
     * for some apps that exist, such as Spacewar, so the cause cannot be recovered. No
     * key goes out on this endpoint, which is what leaves every 403 to this request.
     */
    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        if ($response->status() !== 403) {
            return null;
        }

        return AppNewsUnavailableException::forAppId($this->appId, $response);
    }

    public function createDtoFromResponse(Response $response): AppNews
    {
        /**
         * @var array{appnews: array{appid: int, newsitems: list<array{
         *     gid: string,
         *     title: string,
         *     url: string,
         *     is_external_url: bool,
         *     author: string,
         *     contents: string,
         *     feedlabel: string,
         *     date: int,
         *     feedname: string,
         *     feed_type: int,
         *     appid: int,
         *     tags?: list<string>,
         * }>, count: int}} $body
         */
        $body = $response->json();

        return AppNews::fromArray($body['appnews']);
    }

    /**
     * Steam silently ignores `feeds[0]=…`, so the filters go out comma-joined.
     *
     * @return array<string, int|string>
     */
    protected function defaultQuery(): array
    {
        $query = ['appid' => $this->appId];

        if ($this->count !== null) {
            $query['count'] = $this->count;
        }

        if ($this->maxLength !== null) {
            $query['maxlength'] = $this->maxLength;
        }

        if ($this->endDate instanceof DateTimeInterface) {
            $query['enddate'] = $this->endDate->getTimestamp();
        }

        if ($this->feeds !== []) {
            $query['feeds'] = implode(',', $this->feeds);
        }

        if ($this->tags !== []) {
            $query['tags'] = implode(',', $this->tags);
        }

        return $query;
    }
}
