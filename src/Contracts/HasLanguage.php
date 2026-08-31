<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Contracts;

use Fkrzski\SteamApiSdk\Enums\Language;

/**
 * Marks a request whose endpoint understands the `l` query parameter, so the connector
 * knows where the configured default language may be filled in.
 */
interface HasLanguage
{
    public ?Language $language { get; }
}
