<?php

declare(strict_types=1);
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;
use Saloon\Contracts\Sender;
use Saloon\Http\BaseResource;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
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

arch('hook payloads are final readonly classes')
    ->expect('Fkrzski\SteamApiSdk\Hooks')
    ->toBeClasses()
    ->toBeFinal()
    ->toBeReadonly();

arch('contracts are interfaces')
    ->expect('Fkrzski\SteamApiSdk\Contracts')
    ->toBeInterfaces();

arch('enums are backed enums')
    ->expect('Fkrzski\SteamApiSdk\Enums')
    ->toBeEnums()
    ->toImplement(BackedEnum::class);

arch('requests extend the Saloon Request base class')
    ->expect('Fkrzski\SteamApiSdk\Http\Requests')
    ->toExtend(Request::class);

arch('resources extend the Saloon base resource')
    ->expect('Fkrzski\SteamApiSdk\Http\Resources')
    ->toExtend(BaseResource::class)
    ->toBeFinal();

arch('senders are final and implement the Saloon sender contract')
    ->expect('Fkrzski\SteamApiSdk\Http\Senders')
    ->toBeFinal()
    ->toImplement(Sender::class);

arch('exceptions extend the SDK root exception')
    ->expect('Fkrzski\SteamApiSdk\Exceptions')
    ->classes()
    ->toExtend(SteamApiException::class)
    ->ignoring(SteamApiException::class);

arch('connector is a final Saloon connector')
    ->expect(SteamConnector::class)
    ->toBeFinal()
    ->toExtend(Connector::class);

arch('test factories are final readonly classes')
    ->expect('Fkrzski\SteamApiSdk\Testing\Factories')
    ->toBeClasses()
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveSuffix('Factory');

arch('the response builders are final')
    ->expect(SteamResponse::class)
    ->toBeFinal();

arch('resource fakes are final readonly classes')
    ->expect('Fkrzski\SteamApiSdk\Testing\Fakes')
    ->toBeClasses()
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveSuffix('Fake');

arch('the fake is a final Saloon mock client')
    ->expect(SteamFake::class)
    ->toBeFinal()
    ->toExtend(MockClient::class);

// One target per rule: Pest lets a used class through when expect() gets several.
foreach ([
    'Fkrzski\SteamApiSdk\Contracts',
    'Fkrzski\SteamApiSdk\Dto',
    'Fkrzski\SteamApiSdk\Enums',
    'Fkrzski\SteamApiSdk\Exceptions',
    'Fkrzski\SteamApiSdk\Hooks',
    'Fkrzski\SteamApiSdk\Http',
    'Fkrzski\SteamApiSdk\ValueObjects',
    SteamConfig::class,
    SteamConnector::class,
] as $layer) {
    arch("{$layer} never reaches into the testing helpers")
        ->expect($layer)
        ->not->toUse('Fkrzski\SteamApiSdk\Testing');
}
