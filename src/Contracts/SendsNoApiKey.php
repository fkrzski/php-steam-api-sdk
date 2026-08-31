<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Contracts;

/**
 * Marks an endpoint Steam serves anonymously, so the connector keeps the configured
 * key out of its query.
 */
interface SendsNoApiKey {}
