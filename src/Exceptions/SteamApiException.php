<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\Exceptions;

use RuntimeException;
use Saloon\Http\Response;

class SteamApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?Response $response = null,
    ) {
        parent::__construct($message, $response?->status() ?? 0);
    }
}
