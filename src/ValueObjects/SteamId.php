<?php

declare(strict_types=1);

namespace Fkrzski\SteamApiSdk\ValueObjects;

use Fkrzski\SteamApiSdk\Exceptions\InvalidSteamIdException;
use JsonSerializable;
use Stringable;

final readonly class SteamId implements JsonSerializable, Stringable
{
    private const string STEAM_ID_64_PATTERN = '/^\d{17}$/';

    private const string PROFILE_URL_PATTERN = '#/profiles/(\d{17})(?:[/?\#]|$)#';

    private const string VANITY_URL_PATTERN = '#/id/([^/?\#]+)#';

    private function __construct(
        public string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public static function fromSteamId64(string $value): self
    {
        if (preg_match(self::STEAM_ID_64_PATTERN, $value) !== 1) {
            throw new InvalidSteamIdException(sprintf('"%s" is not a valid 64-bit Steam ID.', $value));
        }

        return new self($value);
    }

    public static function tryFromInput(string $input): ?self
    {
        $input = trim($input);

        if (preg_match(self::STEAM_ID_64_PATTERN, $input) === 1) {
            return new self($input);
        }

        if (preg_match(self::PROFILE_URL_PATTERN, $input, $matches) === 1) {
            return new self($matches[1]);
        }

        return null;
    }

    public static function extractVanityName(string $input): string
    {
        $input = trim($input);

        if (preg_match(self::VANITY_URL_PATTERN, $input, $matches) === 1) {
            return $matches[1];
        }

        return $input;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
