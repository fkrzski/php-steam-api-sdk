<?php

declare(strict_types=1);
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\BaseResource;
use Saloon\Http\Connector;
use Saloon\Http\Request;

arch('PHP best practices preset')
    ->preset()
    ->php();

arch('security preset')
    ->preset()
    ->security();

arch('every source file declares strict types')
    ->expect('Fkrzski\SteamApiSdk')
    ->toUseStrictTypes();

arch('DTOs are final readonly classes')
    ->expect('Fkrzski\SteamApiSdk\Dto')
    ->toBeClasses()
    ->toBeFinal()
    ->toBeReadonly();

arch('value objects are final readonly classes')
    ->expect('Fkrzski\SteamApiSdk\ValueObjects')
    ->toBeClasses()
    ->toBeFinal()
    ->toBeReadonly();

arch('contracts are interfaces')
    ->expect('Fkrzski\SteamApiSdk\Contracts')
    ->toBeInterfaces();

arch('enums are backed enums')
    ->expect('Fkrzski\SteamApiSdk\Enums')
    ->toBeEnums();

arch('requests extend the Saloon Request base class')
    ->expect('Fkrzski\SteamApiSdk\Http\Requests')
    ->toExtend(Request::class);

arch('resources extend the Saloon base resource')
    ->expect('Fkrzski\SteamApiSdk\Http\Resources')
    ->toExtend(BaseResource::class)
    ->toBeFinal();

arch('exceptions extend the SDK root exception')
    ->expect('Fkrzski\SteamApiSdk\Exceptions')
    ->classes()
    ->toExtend(SteamApiException::class)
    ->ignoring(SteamApiException::class);

arch('connector extends the Saloon connector')
    ->expect(SteamConnector::class)
    ->toExtend(Connector::class);
