<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Dto\PlayerBan;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Dto\UserGroup;
use Fkrzski\SteamApiSdk\Enums\CommentPermission;
use Fkrzski\SteamApiSdk\Enums\CommunityVisibility;
use Fkrzski\SteamApiSdk\Enums\FriendRelationship;
use Fkrzski\SteamApiSdk\Enums\PersonaState;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Hooks\ResponseReceived;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerBansRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetPlayerSummariesRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetUserGroupListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Resources\UsersResource;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerSummaryFactory;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;
use Fkrzski\SteamApiSdk\ValueObjects\SteamId;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Saloon\Contracts\Sender;
use Saloon\Data\FactoryCollection;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\Http\Senders\Factories\GuzzleMultipartBodyFactory;

covers(UsersResource::class);

function usersResourceSteamId(): SteamId
{
    return SteamId::fromSteamId64('76561198000000000');
}

function usersResourceMock(string $requestClass, string $fixture): MockClient
{
    return new MockClient([$requestClass => MockResponse::fixture($fixture)]);
}

function usersResource(MockClient $mockClient, SteamConfig $config = new SteamConfig('test-key')): UsersResource
{
    $connector = new SteamConnector($config);
    $connector->withMockClient($mockClient);

    return $connector->users();
}

/**
 * Answers every chunk with the players it asked for, except the chunks given a status.
 *
 * @param  Closure(list<string>): array<string, mixed>  $body
 * @param  array<int, int>  $statuses
 */
function usersResourceSteam(string $requestClass, Closure $body, array $statuses = []): MockClient
{
    $sent = 0;

    return new MockClient([
        $requestClass => static function (PendingRequest $pendingRequest) use ($body, $statuses, &$sent): MockResponse {
            $status = $statuses[$sent++] ?? null;

            return $status === null
                ? MockResponse::make($body(explode(',', (string) $pendingRequest->query()->get('steamids'))))
                : MockResponse::make('{}', $status);
        },
    ]);
}

/**
 * Holds each answer until the pool waits on it, as a real transfer would; last first settles all held in reverse.
 *
 * @param  Closure(list<string>): array<string, mixed>  $body
 * @param  array<int, int>  $statuses
 */
function usersResourceHoldingSender(Closure $body, array $statuses = [], bool $lastFirst = false): Sender
{
    return new class($body, $statuses, $lastFirst) implements Sender
    {
        /**
         * How many answers were held at each wait.
         *
         * @var list<int>
         */
        public array $batches = [];

        /**
         * @var array<int, array{Promise, Response}>
         */
        private array $held = [];

        private int $sent = 0;

        /**
         * @param  array<int, int>  $statuses
         */
        public function __construct(
            private readonly Closure $body,
            private readonly array $statuses,
            private readonly bool $lastFirst,
        ) {}

        public function getFactoryCollection(): FactoryCollection
        {
            $factory = new HttpFactory;

            return new FactoryCollection($factory, $factory, $factory, $factory, new GuzzleMultipartBodyFactory);
        }

        public function send(PendingRequest $pendingRequest): Response
        {
            throw new LogicException('Only a pool sends through this sender.');
        }

        public function sendAsync(PendingRequest $pendingRequest): PromiseInterface
        {
            $index = $this->sent++;
            $psrRequest = $pendingRequest->createPsrRequest();
            $steamIds = explode(',', (string) $pendingRequest->query()->get('steamids'));
            $status = $this->statuses[$index] ?? 200;
            $body = $status === 200 ? (string) json_encode(($this->body)($steamIds)) : '{}';
            $answer = new PsrResponse($status, ['Content-Type' => 'application/json'], $body);

            $promise = new Promise(function () use ($index): void {
                $this->batches[] = count($this->held);

                if (! $this->lastFirst) {
                    [$promise, $response] = $this->held[$index];
                    unset($this->held[$index]);
                    $promise->resolve($response);

                    return;
                }

                while ($held = array_pop($this->held)) {
                    $held[0]->resolve($held[1]);
                }
            });

            $this->held[$index] = [$promise, $pendingRequest->getResponseClass()::fromPsrResponse($answer, $pendingRequest, $psrRequest)];

            return $promise;
        }
    };
}

/**
 * @param  list<PlayerSummary|PlayerBan>  $players
 * @return list<string>
 */
function usersResourceIdsOf(array $players): array
{
    return array_map(static fn (PlayerSummary|PlayerBan $player): string => $player->steamId->value, $players);
}

/**
 * @param  list<SteamId>  $steamIds
 * @return list<string>
 */
function usersResourceValues(array $steamIds): array
{
    return array_map(static fn (SteamId $steamId): string => $steamId->value, $steamIds);
}

/**
 * @return list<int>
 */
function usersResourceChunkSizes(MockClient $mockClient): array
{
    return array_map(
        static fn (Response $response): int => count(explode(',', (string) $response->getPendingRequest()->query()->get('steamids'))),
        $mockClient->getRecordedResponses(),
    );
}

dataset('batched endpoints', [
    'summaries' => [
        GetPlayerSummariesRequest::class,
        'GetPlayerSummaries',
        static fn (UsersResource $users, array $steamIds, int ...$concurrency): array => $users->summaries($steamIds, ...$concurrency),
        static fn (array $steamIds): array => ['response' => ['players' => array_map(static fn (string $steamId): array => [
            'steamid' => $steamId,
            'communityvisibilitystate' => 3,
            'personaname' => 'tester',
            'profileurl' => 'https://steamcommunity.com/profiles/'.$steamId.'/',
            'avatar' => 'https://avatars.steamstatic.com/avatar.jpg',
            'avatarmedium' => 'https://avatars.steamstatic.com/avatar_medium.jpg',
            'avatarfull' => 'https://avatars.steamstatic.com/avatar_full.jpg',
            'avatarhash' => 'avatar',
        ], $steamIds)]],
    ],
    'bans' => [
        GetPlayerBansRequest::class,
        'GetPlayerBans',
        static fn (UsersResource $users, array $steamIds, int ...$concurrency): array => $users->bans($steamIds, ...$concurrency),
        static fn (array $steamIds): array => ['players' => array_map(static fn (string $steamId): array => [
            'SteamId' => $steamId,
            'CommunityBanned' => false,
            'VACBanned' => false,
            'NumberOfVACBans' => 0,
            'DaysSinceLastBan' => 0,
            'NumberOfGameBans' => 0,
            'EconomyBan' => 'none',
        ], $steamIds)],
    ],
]);

test('summaries sends GetPlayerSummaries and returns PlayerSummary DTOs', function (): void {
    $mockClient = usersResourceMock(GetPlayerSummariesRequest::class, 'ISteamUser/GetPlayerSummaries/default');

    $summaries = usersResource($mockClient)->summaries([usersResourceSteamId()]);

    expect($summaries)->toHaveCount(2)
        ->and($summaries[0])->toBeInstanceOf(PlayerSummary::class)
        ->and($summaries[0]->personaName)->toBe('tester-one')
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['steamids' => '76561198000000000']);
});

test('values Steam adds later come back as Unknown without failing the batch', function (): void {
    $mockClient = new MockClient([
        GetPlayerSummariesRequest::class => SteamResponse::playerSummaries(
            PlayerSummaryFactory::new()->state(['steamid' => '76561198000000000', 'personastate' => 7]),
            PlayerSummaryFactory::new()->state([
                'steamid' => '76561198000000001',
                'personastate' => 99,
                'communityvisibilitystate' => 4,
                'commentpermission' => 5,
            ]),
            PlayerSummaryFactory::new()->state(['steamid' => '76561198000000002']),
        ),
    ]);

    $summaries = usersResource($mockClient)->summaries(makeSteamIds(3));

    expect($summaries)->toHaveCount(3)
        ->and($summaries[0]->personaState)->toBe(PersonaState::Invisible)
        ->and($summaries[1]->personaState)->toBe(PersonaState::Unknown)
        ->and($summaries[1]->communityVisibility)->toBe(CommunityVisibility::Unknown)
        ->and($summaries[1]->commentPermission)->toBe(CommentPermission::Unknown)
        ->and($summaries[2]->personaState)->toBe(PersonaState::Offline)
        ->and($summaries[2]->steamId->value)->toBe('76561198000000002');
});

test('bans sends GetPlayerBans and returns PlayerBan DTOs', function (): void {
    $mockClient = usersResourceMock(GetPlayerBansRequest::class, 'ISteamUser/GetPlayerBans/default');

    $bans = usersResource($mockClient)->bans([usersResourceSteamId()]);

    expect($bans)->toHaveCount(2)
        ->and($bans[0])->toBeInstanceOf(PlayerBan::class)
        ->and($bans[1]->isVacBanned)->toBeTrue()
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['steamids' => '76561198000000000']);
});

test('friends sends GetFriendList and returns Friend DTOs', function (): void {
    $mockClient = usersResourceMock(GetFriendListRequest::class, 'ISteamUser/GetFriendList/default');

    $friends = usersResource($mockClient)->friends(usersResourceSteamId());

    expect($friends)->toHaveCount(4)
        ->and($friends[0])->toBeInstanceOf(Friend::class)
        ->and($friends[0]->steamId->value)->toBe('76561198000408055')
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['steamid' => '76561198000000000']);
});

test('friends forwards the relationship filter', function (): void {
    $mockClient = usersResourceMock(GetFriendListRequest::class, 'ISteamUser/GetFriendList/default');

    usersResource($mockClient)->friends(usersResourceSteamId(), FriendRelationship::Friend);

    expect($mockClient->getLastRequest()?->query()->all())->toBe([
        'steamid' => '76561198000000000',
        'relationship' => 'friend',
    ]);
});

test('groups sends GetUserGroupList and returns UserGroup DTOs', function (): void {
    $mockClient = usersResourceMock(GetUserGroupListRequest::class, 'ISteamUser/GetUserGroupList/default');

    $groups = usersResource($mockClient)->groups(usersResourceSteamId());

    expect($groups)->toHaveCount(4)
        ->and($groups[0])->toBeInstanceOf(UserGroup::class)
        ->and($groups[0]->gid)->toBe('4218398');
});

test('resolveVanityUrl sends ResolveVanityURL and returns a SteamId', function (): void {
    $mockClient = usersResourceMock(ResolveVanityUrlRequest::class, 'ISteamUser/ResolveVanityUrl/success');

    $steamId = usersResource($mockClient)->resolveVanityUrl('gabelogannewell');

    expect($steamId)->toBeInstanceOf(SteamId::class)
        ->and($steamId->value)->toBe('76561198000000000')
        ->and($mockClient->getLastRequest()?->query()->all())->toBe(['vanityurl' => 'gabelogannewell']);
});

test('a list over 100 ids is sent in chunks of 100 and comes back in chunk order', function (string $requestClass, string $method, Closure $call, Closure $body): void {
    $mockClient = usersResourceSteam($requestClass, $body);
    $steamIds = makeSteamIds(250);

    $players = $call(usersResource($mockClient), $steamIds);

    expect(usersResourceChunkSizes($mockClient))->toBe([100, 100, 50])
        ->and(usersResourceIdsOf($players))->toBe(usersResourceValues($steamIds));
})->with('batched endpoints');

test('100 ids go out as one request and 101 as two', function (string $requestClass, string $method, Closure $call, Closure $body): void {
    $one = usersResourceSteam($requestClass, $body);
    $two = usersResourceSteam($requestClass, $body);

    $call(usersResource($one), makeSteamIds(100));
    $call(usersResource($two), makeSteamIds(101));

    expect(usersResourceChunkSizes($one))->toBe([100])
        ->and(usersResourceChunkSizes($two))->toBe([100, 1]);
})->with('batched endpoints');

test('the result keeps chunk order when the answers arrive last first', function (string $requestClass, string $method, Closure $call, Closure $body): void {
    $steamIds = makeSteamIds(250);
    $users = new SteamConnector(new SteamConfig('test-key', sender: usersResourceHoldingSender($body, lastFirst: true)))->users();

    expect(usersResourceIdsOf($call($users, $steamIds)))->toBe(usersResourceValues($steamIds));
})->with('batched endpoints');

test('chunks go out five at a time unless told otherwise', function (string $requestClass, string $method, Closure $call, Closure $body, array $concurrency, array $batches): void {
    $sender = usersResourceHoldingSender($body, lastFirst: true);

    $call(new SteamConnector(new SteamConfig('test-key', sender: $sender))->users(), makeSteamIds(700), ...$concurrency);

    expect($sender->batches)->toBe($batches);
})->with('batched endpoints')->with([
    'default' => [[], [5, 2]],
    'two' => [[2], [2, 2, 2, 1]],
]);

test('a concurrency under 1 is rejected before anything is sent', function (string $requestClass, string $method, Closure $call, Closure $body, int $concurrency): void {
    $mockClient = usersResourceSteam($requestClass, $body);

    expect(fn (): array => $call(usersResource($mockClient), makeSteamIds(101), $concurrency))
        ->toThrow(function (InvalidArgumentException $exception) use ($concurrency): void {
            expect($exception->getMessage())->toBe(sprintf('Concurrency must be at least 1, %d given.', $concurrency));
        });

    $mockClient->assertNothingSent();
})->with('batched endpoints')->with([0, -1]);

test('an empty list is still rejected by the request', function (string $requestClass, string $method, Closure $call, Closure $body): void {
    $mockClient = usersResourceSteam($requestClass, $body);

    expect(fn (): array => $call(usersResource($mockClient), []))
        ->toThrow(function (InvalidArgumentException $exception) use ($requestClass): void {
            expect($exception->getMessage())->toBe(sprintf('%s requires at least one SteamId.', new ReflectionClass($requestClass)->getShortName()));
        });
})->with('batched endpoints');

test('a list of up to 100 ids keeps the retries', function (string $requestClass, string $method, Closure $call, Closure $body): void {
    $mockClient = usersResourceSteam($requestClass, $body, [0 => 500]);

    $players = $call(usersResource($mockClient, new SteamConfig('test-key', tries: 2)), makeSteamIds(100));

    expect($players)->toHaveCount(100);
    $mockClient->assertSentCount(2);
})->with('batched endpoints');

test('each chunk is sent once whatever tries says', function (string $requestClass, string $method, Closure $call, Closure $body): void {
    $mockClient = usersResourceSteam($requestClass, $body, [0 => 500]);

    expect(fn (): array => $call(usersResource($mockClient, new SteamConfig('test-key', tries: 3)), makeSteamIds(101)))
        ->toThrow(SteamApiException::class);

    $mockClient->assertSentCount(2);
})->with('batched endpoints');

test('a failed chunk keeps the chunks not yet sent from going out', function (string $requestClass, string $method, Closure $call, Closure $body): void {
    $mockClient = usersResourceSteam($requestClass, $body, [1 => 500]);

    expect(fn (): array => $call(usersResource($mockClient), makeSteamIds(250), 1))
        ->toThrow(function (SteamApiException $exception) use ($method): void {
            expect($exception->getMessage())->toBe(sprintf('%s: Steam API request failed with HTTP 500.', $method));
        });

    $mockClient->assertSentCount(2);
})->with('batched endpoints');

test('chunks in flight still answer and count before the first failure is thrown', function (string $requestClass, string $method, Closure $call, Closure $body): void {
    $connector = new SteamConnector(new SteamConfig('test-key', sender: usersResourceHoldingSender($body, [0 => 500, 1 => 503])));
    $answered = [];
    $failures = [];

    $connector
        ->onResponse(static function (ResponseReceived $response) use (&$answered): void {
            $answered[] = $response->status;
        })
        ->onFailure(static function (Throwable $exception) use (&$failures): void {
            $failures[] = $exception->getCode();
        });

    expect(fn (): array => $call($connector->users(), makeSteamIds(250), 3))
        ->toThrow(function (SteamApiException $exception) use ($method): void {
            expect($exception->getMessage())->toBe(sprintf('%s: Steam API request failed with HTTP 500.', $method));
        });

    expect($answered)->toBe([500, 503, 200])
        ->and($failures)->toBe([500, 503])
        ->and(dailyHits($connector))->toBe(3);
})->with('batched endpoints');
