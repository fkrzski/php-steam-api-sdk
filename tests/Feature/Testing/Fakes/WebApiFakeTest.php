<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetServerInfoRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetSupportedApiListRequest;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiInterfaceFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiMethodFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamWebAPIUtil\WebApiParameterFactory;
use Fkrzski\SteamApiSdk\Testing\Fakes\WebApiFake;
use Fkrzski\SteamApiSdk\Testing\SteamFake;
use Saloon\Http\Request;

covers(WebApiFake::class);

// Envelopes

test('writes the pacific string without moving a mutable date', function (): void {
    $time = new DateTime('2026-10-12 19:31:26 UTC');

    $answer = answerFrom(new SteamFake()->webApi()->serverInfo($time), new GetServerInfoRequest);

    expect($answer->body())->toBe(json_encode([
        'servertime' => 1791833486,
        'servertimestring' => 'Mon Oct 12 12:31:26 2026',
    ], JSON_THROW_ON_ERROR))
        ->and($time->getTimezone()->getName())->toBe('UTC');
});

test('nests the supported api list under apilist.interfaces', function (): void {
    $answer = answerFrom(
        new SteamFake()->webApi()->supportedApiList(WebApiInterfaceFactory::new(), WebApiInterfaceFactory::new()->name('IGCVersion_440')),
        new GetSupportedApiListRequest,
    );

    expect($answer->body())->toBe(json_encode([
        'apilist' => [
            'interfaces' => [
                WebApiInterfaceFactory::new()->toArray(),
                WebApiInterfaceFactory::new()->name('IGCVersion_440')->toArray(),
            ],
        ],
    ], JSON_THROW_ON_ERROR));
});

test('answers an empty supported api list with no interfaces', function (): void {
    $answer = answerFrom(new SteamFake()->webApi()->supportedApiList(), new GetSupportedApiListRequest);

    expect($answer->body())->toBe('{"apilist":{"interfaces":[]}}');
});

test('sends the body steam was captured sending', function (Closure $fake, Request $request, string $fixture): void {
    $answer = answerFrom($fake(new SteamFake), $request);

    expect([$answer->status(), $answer->body()])->toBe(captured($fixture));
})->with([
    'supportedApiListInvalidKey()' => [
        fn (SteamFake $fake): SteamFake => $fake->webApi()->supportedApiListInvalidKey(),
        new GetSupportedApiListRequest,
        'ISteamWebAPIUtil/GetSupportedAPIList/invalid-key',
    ],
    'serverInfo()' => [
        fn (SteamFake $fake): SteamFake => $fake->webApi()->serverInfo(new DateTimeImmutable('2026-10-08 19:59:38 UTC')),
        new GetServerInfoRequest,
        'ISteamWebAPIUtil/GetServerInfo/default',
    ],
]);

// Round trips through the connector

test('fakes every web api endpoint through the chain', function (): void {
    $fake = new SteamFake()
        ->webApi()->serverInfo(new DateTimeImmutable('2026-10-08 21:59:38 +02:00'))
        ->webApi()->supportedApiList(
            WebApiInterfaceFactory::new()->name('IGCVersion_440')->methods(
                WebApiMethodFactory::new()->name('GetClientVersion')->withoutDescription()->parameters(),
            ),
            WebApiInterfaceFactory::new()->methods(
                WebApiMethodFactory::new()->name('PollAuthSessionStatus')->post()->parameters(
                    WebApiParameterFactory::new()->name('client_id')->type('uint64')->withoutDescription(),
                    WebApiParameterFactory::new()->emptyDescription(),
                    WebApiParameterFactory::new()->optional(),
                ),
            ),
        );
    $connector = fakedConnector($fake);

    [$gcVersion, $authentication] = $connector->webApi()->supportedApis();
    $parameters = $authentication->methods[0]->parameters;

    expect($connector->webApi()->serverTime()->format(DATE_ATOM))->toBe('2026-10-08T19:59:38+00:00')
        ->and($gcVersion->name)->toBe('IGCVersion_440')
        ->and($gcVersion->methods[0]->description)->toBeNull()
        ->and($gcVersion->methods[0]->httpMethod)->toBe('GET')
        ->and($gcVersion->methods[0]->parameters)->toBeEmpty()
        ->and($authentication->methods[0]->httpMethod)->toBe('POST')
        ->and($parameters[0]->type)->toBe('uint64')
        ->and($parameters[0]->description)->toBeNull()
        ->and($parameters[1]->description)->toBeNull()
        ->and($parameters[2]->isOptional)->toBeTrue();

    $fake->assertSentCount(2);
});

// Failures — these pin the contract with the SDK's exception mapping

// The connector reads a 403 without `key=` as a hidden profile; the request claims this one first.
test('raises an invalid api key from a key the supported api list rejects', function (): void {
    $fake = new SteamFake;

    expect($fake->webApi()->supportedApiListInvalidKey())->toBe($fake)
        ->and(fn (): array => fakedConnector($fake)->webApi()->supportedApis())->toThrow(
            InvalidApiKeyException::class,
            'GetSupportedAPIList: Steam rejected the API key. Check that it is valid and active.',
        );
});
