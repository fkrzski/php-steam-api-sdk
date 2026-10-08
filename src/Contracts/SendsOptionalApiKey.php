<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Contracts;

/**
 * Marks an endpoint Steam serves with a key or without, so the connector sends the
 * configured key when there is one and goes out without it otherwise.
 */
interface SendsOptionalApiKey {}
