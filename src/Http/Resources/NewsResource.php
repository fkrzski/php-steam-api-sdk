<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Http\Resources;

use DateTimeInterface;
use Fkrzski\SteamApiSdk\Dto\AppNews;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamNews\GetNewsForAppRequest;
use Saloon\Http\BaseResource;

final class NewsResource extends BaseResource
{
    /**
     * @param  list<string>  $feeds
     * @param  list<string>  $tags
     */
    public function appNews(
        int $appId,
        ?int $count = null,
        ?int $maxLength = null,
        ?DateTimeInterface $endDate = null,
        array $feeds = [],
        array $tags = [],
    ): AppNews {
        $request = new GetNewsForAppRequest($appId, $count, $maxLength, $endDate, $feeds, $tags);

        return $request->createDtoFromResponse($this->connector->send($request));
    }
}
