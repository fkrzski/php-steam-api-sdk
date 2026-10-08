<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\WebApiInterface;
use Fkrzski\SteamApiSdk\Dto\WebApiMethod;
use Fkrzski\SteamApiSdk\Dto\WebApiParameter;
use Fkrzski\SteamApiSdk\Exceptions\InvalidApiKeyException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetSupportedApiListRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

covers([GetSupportedApiListRequest::class, WebApiInterface::class, WebApiMethod::class, WebApiParameter::class, InvalidApiKeyException::class]);

function supportedApiListMock(string $fixture): MockClient
{
    return new MockClient([
        GetSupportedApiListRequest::class => MockResponse::fixture(sprintf('ISteamWebAPIUtil/GetSupportedAPIList/%s', $fixture)),
    ]);
}

/**
 * @return list<WebApiInterface>
 */
function sendSupportedApiListFixture(string $fixture, SteamConfig $config = new SteamConfig('test-key')): array
{
    $connector = new SteamConnector($config);
    $connector->withMockClient(supportedApiListMock($fixture));

    /** @var list<WebApiInterface> $interfaces */
    $interfaces = $connector->send(new GetSupportedApiListRequest)->dto();

    return $interfaces;
}

/**
 * @param  list<WebApiInterface>  $interfaces
 */
function supportedApiMethod(array $interfaces, string $interface, string $method, int $version = 1): WebApiMethod
{
    foreach ($interfaces as $candidate) {
        foreach ($candidate->methods as $candidateMethod) {
            if ($candidate->name === $interface && $candidateMethod->name === $method && $candidateMethod->version === $version) {
                return $candidateMethod;
            }
        }
    }

    throw new RuntimeException(sprintf('No %s/%s/v%d in the list.', $interface, $method, $version));
}

test('endpoint targets GetSupportedAPIList v1', function (): void {
    expect((new GetSupportedApiListRequest)->resolveEndpoint())->toBe('/ISteamWebAPIUtil/GetSupportedAPIList/v1/');
});

test('query carries no parameters', function (): void {
    expect((new GetSupportedApiListRequest)->query()->all())->toBe([]);
});

test('interfaces come back in Steam order under the names Steam gives them', function (): void {
    $interfaces = sendSupportedApiListFixture('anonymous');

    expect(array_map(static fn (WebApiInterface $interface): string => $interface->name, $interfaces))
        ->toBe(['IClientStats_1046930', 'IGCVersion_440', 'ISteamWebAPIUtil', 'IStoreService']);
});

test('a method maps its version, HTTP method and parameters', function (): void {
    $method = supportedApiMethod(sendSupportedApiListFixture('anonymous'), 'ISteamWebAPIUtil', 'GetSupportedAPIList');

    expect($method->version)->toBe(1)
        ->and($method->httpMethod)->toBe('GET')
        ->and($method->description)->toBeNull()
        ->and($method->parameters)->toEqual([new WebApiParameter('key', 'string', true, 'access key')]);
});

test('a method without parameters carries an empty list', function (): void {
    $method = supportedApiMethod(sendSupportedApiListFixture('anonymous'), 'ISteamWebAPIUtil', 'GetServerInfo');

    expect($method->parameters)->toBe([]);
});

test('a POST method keeps its HTTP method', function (): void {
    $method = supportedApiMethod(sendSupportedApiListFixture('anonymous'), 'IClientStats_1046930', 'ReportEvent');

    expect($method->httpMethod)->toBe('POST');
});

test('a description Steam leaves out comes back null', function (): void {
    $method = supportedApiMethod(sendSupportedApiListFixture('anonymous'), 'IStoreService', 'GetGamesFollowedCount');

    expect($method->description)->toBe('Get the number of games a user is following')
        ->and($method->parameters)->toEqual([new WebApiParameter('steamid', 'uint64', false, null)]);
});

test('a parameter description Steam sends empty comes back null', function (): void {
    $method = supportedApiMethod(sendSupportedApiListFixture('keyed'), 'IDOTA2MatchStats_570', 'GetRealtimeStats');

    expect($method->parameters[0]->description)->toBeNull();
});

test('each version of a method is its own entry', function (): void {
    $interfaces = sendSupportedApiListFixture('keyed');

    expect(supportedApiMethod($interfaces, 'ISteamUser', 'GetPlayerSummaries', 2)->parameters[1]->description)
        ->toBe('Comma-delimited list of SteamIDs (max: 100)')
        ->and(supportedApiMethod($interfaces, 'ISteamUser', 'GetPlayerSummaries')->parameters[1]->description)
        ->toBe('Comma-delimited list of SteamIDs');
});

test('the configured key goes out on the query', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $mockClient = supportedApiListMock('keyed');
    $connector->withMockClient($mockClient);

    $connector->send(new GetSupportedApiListRequest);

    expect($mockClient->getLastPendingRequest()?->query()->all())->toBe(['key' => 'test-key']);
});

test('a connector without an API key sends it with no key at all', function (): void {
    $connector = new SteamConnector(new SteamConfig);
    $mockClient = supportedApiListMock('anonymous');
    $connector->withMockClient($mockClient);

    /** @var list<WebApiInterface> $interfaces */
    $interfaces = $connector->send(new GetSupportedApiListRequest)->dto();

    expect($mockClient->getLastPendingRequest()?->query()->all())->toBe([])
        ->and($interfaces)->toHaveCount(4);
});

test('a rejected key throws InvalidApiKeyException carrying the 403', function (): void {
    try {
        sendSupportedApiListFixture('invalid-key');
    } catch (InvalidApiKeyException $invalidApiKeyException) {
        expect($invalidApiKeyException->getMessage())
            ->toBe('GetSupportedAPIList: Steam rejected the API key. Check that it is valid and active.')
            ->and($invalidApiKeyException->getCode())->toBe(403);

        return;
    }

    throw new RuntimeException('Expected the key to be rejected.');
});

test('a failure other than the rejected key is left to the connector', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));
    $connector->withMockClient(new MockClient([
        GetSupportedApiListRequest::class => MockResponse::make([], 500),
    ]));

    $connector->send(new GetSupportedApiListRequest);
})->throws(
    SteamApiException::class,
    'Steam API request failed with HTTP 500.',
);
