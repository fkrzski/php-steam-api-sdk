<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Contracts;

use Saloon\Http\Response;

/**
 * Names the dot paths every readable 2xx answer to the method carries, so an answer without
 * one fails before the request decides on it or builds its result.
 *
 * @internal
 */
interface HasRequiredPaths
{
    /**
     * @return list<string>
     */
    public function requiredPaths(Response $response): array;
}
