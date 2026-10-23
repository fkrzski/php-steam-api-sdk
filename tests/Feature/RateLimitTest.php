<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Hooks\RequestSending;
use Fkrzski\SteamApiSdk\Hooks\ResponseReceived;
use Fkrzski\SteamApiSdk\Http\RateLimiter;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\GetFriendListRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUser\ResolveVanityUrlRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamUserStats\GetNumberOfCurrentPlayersRequest;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetSupportedApiListRequest;
use Fkrzski\SteamApiSdk\Http\Responses\SteamApiResponse;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Saloon\Config;
use Saloon\Contracts\Sender;
use Saloon\Data\FactoryCollection;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;

covers([SteamRateLimitException::class, SteamConnector::class, RateLimiter::class, ApiKeyNotConfiguredException::class, SteamApiResponse::class]);

test("the connector's rate limit checks read the configured key's budget", function (): void {
    $store = new MemoryStore;
    $connector = new SteamConnector(new SteamConfig('test-key', $store));
    $daily = $connector->getLimits()[0];

    $daily->hit(90_000)->save($connector->rateLimitStore());

    expect(array_map(static fn (Limit $limit): string => $limit->getName(), $connector->getLimits()))->toBe([
        'SteamConnector:'.hash('sha256', 'test-key').':100000_every_utc_midnight',
        'SteamConnector:'.hash('sha256', 'test-key').':too_many_attempts_limit',
    ])
        ->and($connector->rateLimitStore())->toBe($store)
        ->and($connector->hasReachedRateLimit(0.9))->toBeTrue()
        ->and($connector->hasReachedRateLimit())->toBeFalse()
        ->and($connector->getExceededLimit(0.9)?->getName())->toBe($daily->getName())
        ->and($connector->getExceededLimit())->toBeNull();
});

test('a connector without a key checks the anonymous budget', function (): void {
    $names = array_map(
        static fn (Limit $limit): string => $limit->getName(),
        new SteamConnector(new SteamConfig)->getLimits(),
    );

    expect($names)->toBe(['SteamConnector:anonymous:too_many_attempts_limit']);
});

test('anonymous requests leave no daily counter behind', function (): void {
    sendCurrentPlayersRequest();
    sendCurrentPlayersRequest();

    expect(dailyLimitKeys())->toBeEmpty();
});

test('Steam throttling anonymous traffic still raises SteamRateLimitException', function (Closure $send): void {
    $connector = new SteamConnector(new SteamConfig);

    $connector->withMockClient(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::make([], 429, ['Retry-After' => '120']),
    ]));

    expect(fn (): mixed => $send($connector, new GetNumberOfCurrentPlayersRequest(381210)))
        ->toThrow(SteamRateLimitException::class);
})->with([
    'send' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->send($request)],
    'sendAsync' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->sendAsync($request)->wait()],
]);

test('a keyless request goes through while the key is paused', function (Closure $pause): void {
    $connector = fakedConnector($mockClient = new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::make([], 429, ['Retry-After' => '120']),
        GetNumberOfCurrentPlayersRequest::class => MockResponse::fixture('ISteamUserStats/GetNumberOfCurrentPlayers/default'),
    ]));

    $pause($connector);

    expect($connector->send(new GetNumberOfCurrentPlayersRequest(381210))->status())->toBe(200);

    $answered = count($mockClient->getRecordedResponses());

    expect(fn (): Response => $connector->send(new ResolveVanityUrlRequest('nick')))->toThrow(SteamRateLimitException::class)
        ->and($mockClient->getRecordedResponses())->toHaveCount($answered);
})->with([
    'after a 429' => [static function (SteamConnector $connector): void {
        rateLimitOf(fn (): Response => $connector->send(new ResolveVanityUrlRequest('nick')));
    }],
    'with the budget spent' => [static function (SteamConnector $connector): void {
        $connector->getLimits()[0]->hit(100_000)->save($connector->rateLimitStore());
    }],
]);

test('a keyless request leaves the daily budget untouched', function (): void {
    $connector = fakedConnector(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::fixture('ISteamUserStats/GetNumberOfCurrentPlayers/default'),
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]));

    $connector->send(new GetNumberOfCurrentPlayersRequest(381210));
    $connector->send(new ResolveVanityUrlRequest('nick'));
    $connector->send(new GetNumberOfCurrentPlayersRequest(381210));

    expect(dailyHits($connector))->toBe(1);
});

test('a request that sends the optional key spends the daily budget', function (): void {
    $connector = fakedConnector(new MockClient([
        GetSupportedApiListRequest::class => MockResponse::fixture('ISteamWebAPIUtil/GetSupportedAPIList/keyed'),
    ]));

    $connector->send(new GetSupportedApiListRequest);

    expect(dailyHits($connector))->toBe(1);
});

test('a 429 on a keyless request pauses only keyless requests', function (): void {
    $connector = fakedConnector($mockClient = new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::make([], 429),
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]), new SteamConfig('test-key', tooManyRequestsCooldown: 300));

    $throttled = rateLimitOf(fn (): Response => $connector->send(new GetNumberOfCurrentPlayersRequest(381210)));

    expect($throttled->limit->getName())->toBe('SteamConnector:anonymous:too_many_attempts_limit')
        ->and($throttled->limit->getRemainingSeconds())->toBeGreaterThan(60)->toBeLessThanOrEqual(300)
        ->and($connector->send(new ResolveVanityUrlRequest('nick'))->status())->toBe(200)
        ->and(fn (): Response => $connector->send(new GetNumberOfCurrentPlayersRequest(381210)))->toThrow(SteamRateLimitException::class)
        ->and($mockClient->getRecordedResponses())->toHaveCount(2);
});

test('a 429 on a keyless request in a pool leaves keyed requests alone', function (): void {
    $thrown = [];
    $connector = fakedConnector(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::make([], 429, ['Retry-After' => '120']),
        GetFriendListRequest::class => MockResponse::make('{"friendslist":{"friends":[]}}'),
    ]));

    // Both go out before either answers, so the limiter cannot hang on whichever request booted last.
    $connector->pool(
        [new GetNumberOfCurrentPlayersRequest(381210), friendListRequest()],
        exceptionHandler: static function (Throwable $reason) use (&$thrown): void {
            $thrown[] = $reason::class;
        },
    )->send()->wait();

    expect($thrown)->toBe([SteamRateLimitException::class])
        ->and(dailyHits($connector))->toBe(1)
        ->and($connector->send(friendListRequest())->status())->toBe(200)
        ->and(fn (): Response => $connector->send(new GetNumberOfCurrentPlayersRequest(381210)))->toThrow(SteamRateLimitException::class);
});

test('configured limits meter keyless requests apart from the key', function (): void {
    $connector = fakedConnector(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::fixture('ISteamUserStats/GetNumberOfCurrentPlayers/default'),
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]), new SteamConfig('test-key', rateLimits: [Limit::allow(1)->everyMinute()]));

    $connector->send(new ResolveVanityUrlRequest('nick'));
    $connector->send(new GetNumberOfCurrentPlayersRequest(381210));

    expect(fn (): Response => $connector->send(new GetNumberOfCurrentPlayersRequest(381210)))
        ->toThrow(function (SteamRateLimitException $steamRateLimitException): void {
            expect($steamRateLimitException->limit->getName())->toBe('SteamConnector:anonymous:1_every_60');
        });
});

test('a keyless request keeps its counters in the configured store', function (): void {
    $store = new class implements RateLimitStore
    {
        /**
         * @var array<string, string>
         */
        public array $values = [];

        public function get(string $key): ?string
        {
            return $this->values[$key] ?? null;
        }

        public function set(string $key, string $value, int $ttl): bool
        {
            $this->values[$key] = $value;

            return true;
        }
    };

    $connector = fakedConnector(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::fixture('ISteamUserStats/GetNumberOfCurrentPlayers/default'),
    ]), new SteamConfig('test-key', $store));

    $connector->send(new GetNumberOfCurrentPlayersRequest(381210));

    expect(array_keys($store->values))->toBe(['SteamConnector:anonymous:too_many_attempts_limit'])
        ->and((new MemoryStore)->getStore())->toBeEmpty();
});

test('turning the limiter off covers keyless requests too', function (): void {
    $connector = fakedConnector(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::make([], 429, ['Retry-After' => '120']),
    ]))->useRateLimitPlugin(false);

    expect(fn (): Response => $connector->send(new GetNumberOfCurrentPlayersRequest(381210)))
        ->toThrow(function (SteamApiException $steamApiException): void {
            expect($steamApiException)->not->toBeInstanceOf(SteamRateLimitException::class)
                ->and($steamApiException->getCode())->toBe(429);
        });
});

test('a pool counts every answer Steam sends against the budget', function (Closure $answering, array $statuses): void {
    $connector = $answering($statuses);
    $answered = [];

    $connector->pool(
        array_map(friendListRequest(...), $statuses),
        responseHandler: static function (Response $response) use (&$answered): void {
            $answered[] = $response->status();
        },
        exceptionHandler: static function (SteamApiException $exception) use (&$answered): void {
            $answered[] = $exception->getCode();
        },
    )->send()->wait();

    expect($answered)->toEqualCanonicalizing($statuses)
        ->and(dailyHits($connector))->toBe(count($statuses));
})->with([
    'MockClient' => [static fn (array $statuses): SteamConnector => new SteamConnector(new SteamConfig('test-key'))->withMockClient(new MockClient(array_map(
        static fn (int $status): MockResponse => MockResponse::make('{}', $status),
        $statuses,
    )))],
    'Guzzle handler' => [static fn (array $statuses): SteamConnector => connectorAnswering(
        array_map(static fn (int $status): PsrResponse => new PsrResponse($status, [], '{}'), $statuses),
        new SteamConfig('test-key'),
    )],
])->with([
    'all succeed' => [[200, 200, 200]],
    'all fail' => [[403, 404, 503]],
    'some fail' => [[200, 403, 200, 503]],
]);

test('a 429 in a pool refuses the requests queued after it', function (): void {
    $sent = [];
    $thrown = [];
    $friendList = new PsrResponse(200, [], '{"friendslist":{"friends":[]}}');
    $connector = connectorAnswering(
        [new PsrResponse(429, ['Retry-After' => '120']), $friendList, $friendList],
        new SteamConfig('test-key'),
        $sent,
    );

    $connector->pool(
        [friendListRequest(), friendListRequest()],
        concurrency: 1,
        exceptionHandler: static function (Throwable $reason) use (&$thrown): void {
            $thrown[] = $reason::class;
        },
    )->send()->wait();

    expect($thrown)->toBe([SteamRateLimitException::class, SteamRateLimitException::class])
        ->and(fn (): mixed => $connector->send(friendListRequest()))->toThrow(SteamRateLimitException::class)
        ->and($sent)->toHaveCount(1)
        ->and(dailyHits($connector))->toBe(1);
});

test('a 429 without Retry-After refuses the requests after it for the configured cool-down', function (Closure $send): void {
    $sent = [];
    $connector = connectorAnswering([new PsrResponse(429)], new SteamConfig('test-key', tooManyRequestsCooldown: 300), $sent);

    $before = time();
    rateLimitOf(fn (): mixed => $send($connector, friendListRequest()));
    $refused = rateLimitOf(fn (): mixed => $send($connector, friendListRequest()));
    $after = time();

    expect($refused->limit->getExpiryTimestamp())->toBeGreaterThanOrEqual($before + 300)->toBeLessThanOrEqual($after + 300)
        ->and($refused->limit->getRemainingSeconds())->toBeGreaterThan(60)->toBeLessThanOrEqual(300)
        ->and($sent)->toHaveCount(1);
})->with([
    'send' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->send($request)],
    'pool' => [static function (SteamConnector $connector, Request $request): never {
        $reasons = [];

        $connector->pool(
            [$request],
            exceptionHandler: static function (Throwable $reason) use (&$reasons): void {
                $reasons[] = $reason;
            },
        )->send()->wait();

        throw $reasons[0] ?? new LogicException('Expected the pool to fail.');
    }],
]);

test('requests go through again once the cool-down passes', function (): void {
    $sent = [];
    $connector = connectorAnswering(
        [new PsrResponse(429), new PsrResponse(200, [], '{"friendslist":{"friends":[]}}')],
        new SteamConfig('test-key', tooManyRequestsCooldown: 1),
        $sent,
    );

    rateLimitOf(fn (): Response => $connector->send(friendListRequest()));
    $refused = rateLimitOf(fn (): Response => $connector->send(friendListRequest()));

    // The plugin's clock, not time_sleep_until()'s: on Windows that one can wake a few ms short of the second.
    while (new DateTimeImmutable()->getTimestamp() <= $refused->limit->getExpiryTimestamp()) {
        usleep(10_000);
    }

    expect($connector->send(friendListRequest())->status())->toBe(200)
        ->and($sent)->toHaveCount(2);
});

test("Steam's 429 raises an exception that carries its answer, and the lock it leaves refuses with none", function (Closure $send, Closure $request, string $method): void {
    $connector = fakedConnector(new MockClient([
        MockResponse::make([], 429, ['Retry-After' => '120']),
        MockResponse::make([]),
    ]));

    $throttled = rateLimitOf(fn (): mixed => $send($connector, $request()));
    $refused = rateLimitOf(fn (): mixed => $send($connector, $request()));

    expect($throttled->getCode())->toBe(429)
        ->and($throttled->response?->header('Retry-After'))->toBe('120')
        ->and($throttled->getMessage())->toBe(sprintf('%s: Steam API rate limit reached (limit: %s).', $method, $throttled->limit->getName()))
        ->and($refused->response)->toBeNull()
        ->and($refused->getCode())->toBe(0)
        ->and($refused->getMessage())->toBe(sprintf('Steam API rate limit reached (limit: %s).', $refused->limit->getName()));
})->with(everyWayToSend())->with([
    'with the key' => [friendListRequest(...), 'GetFriendList'],
    'without the key' => [static fn (): Request => new GetNumberOfCurrentPlayersRequest(381210), 'GetNumberOfCurrentPlayers'],
]);

test('a request Steam never answered costs nothing from the budget', function (Closure $send): void {
    $connector = connectorAnswering([connectionFailure()], new SteamConfig('test-key'));

    expect(fn (): mixed => $send($connector, friendListRequest()))->toThrow(SteamConnectionException::class)
        ->and(dailyHits($connector))->toBe(0);
})->with([
    'send' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->send($request)],
    'sendAsync' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->sendAsync($request)->wait()],
]);

test('a request refused for a missing API key never reaches the wire', function (): void {
    $connector = new SteamConnector(new SteamConfig);

    $connector->withMockClient($mockClient = new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]));

    expect(fn (): mixed => $connector->send(new ResolveVanityUrlRequest('nick')))
        ->toThrow(ApiKeyNotConfiguredException::class)
        ->and($mockClient->getRecordedResponses())->toBeEmpty();
});

test('Steam throttling still raises SteamRateLimitException without local limits', function (Closure $send): void {
    $connector = new SteamConnector(new SteamConfig('test-key', rateLimits: []));

    $connector->withMockClient(new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::make([], 429, ['Retry-After' => '120']),
    ]));

    expect(fn (): mixed => $send($connector, new ResolveVanityUrlRequest('nick')))
        ->toThrow(SteamRateLimitException::class);
})->with([
    'send' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->send($request)],
    'sendAsync' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->sendAsync($request)->wait()],
]);

test('a configured limit set to sleep() waits instead of throwing', function (Closure $send): void {
    $slept = [];
    Config::sleepUsing(static function (int $microseconds) use (&$slept): void {
        $slept[] = $microseconds;
    });

    try {
        $connector = new SteamConnector(new SteamConfig('test-key', rateLimits: [Limit::allow(1)->everyMinute()->sleep()]));

        $connector->withMockClient(new MockClient([
            ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
        ]));

        $send($connector, new ResolveVanityUrlRequest('first'));

        expect($send($connector, new ResolveVanityUrlRequest('second')))->toBeInstanceOf(Response::class)
            ->and($slept)->toHaveCount(1)
            ->and($slept[0])->toBeGreaterThan(0)->toBeLessThanOrEqual(60_000_000);
    } finally {
        Config::sleepUsing(null);
    }
})->with([
    'send' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->send($request)],
    'sendAsync' => [static fn (SteamConnector $connector, Request $request): mixed => $connector->sendAsync($request)->wait()],
])->skip(! method_exists(Config::class, 'sleepUsing'), 'Saloon before 4.4 has no sleep handler, so the test would sleep for real.');

test('a sleep() limit that re-sends keeps numbering the attempts and fires onFailure once', function (Closure $send, int $tries, array $statuses, array $numbered): void {
    $answered = 0;
    $attempts = [];
    $failures = [];
    // A window of no seconds is over by the time the plugin re-sends, so nothing sleeps.
    $connector = new SteamConnector(new SteamConfig('test-key', tries: $tries, rateLimits: [
        Limit::custom(static function (Response $response, Limit $limit) use (&$answered): void {
            if ($answered++ === 0) {
                $limit->exceeded();
            }
        })->everySeconds(0, 'at_once')->sleep(),
    ]))
        ->onRequest(static function (RequestSending $sending) use (&$attempts): void {
            $attempts[] = $sending->attempt;
        })
        ->onFailure(static function (Throwable $throwable) use (&$failures): void {
            $failures[] = $throwable;
        });

    $mockClient = new MockClient(array_map(static fn (int $status): MockResponse => MockResponse::make([], $status), $statuses));

    $failure = failureFrom(fn (): mixed => $send($connector, $mockClient));

    expect($attempts)->toBe($numbered)
        ->and($failures)->toBe([$failure])
        ->and($failure->getCode())->toBe(array_last($statuses))
        ->and($mockClient->getRecordedResponses())->toHaveCount(count($statuses));
})->with([
    'send' => [static fn (SteamConnector $connector, MockClient $mockClient): mixed => $connector->withMockClient($mockClient)->send(friendListRequest())],
    'send with its own mock client' => [static fn (SteamConnector $connector, MockClient $mockClient): mixed => $connector->send(friendListRequest(), $mockClient)],
    'sendAsync with its own mock client' => [static fn (SteamConnector $connector, MockClient $mockClient): mixed => $connector->sendAsync(friendListRequest(), $mockClient)->wait()],
    'pool' => [static fn (SteamConnector $connector, MockClient $mockClient): mixed => everyWayToSend()['pool'][0]($connector->withMockClient($mockClient))],
])->with([
    'the re-send fails' => [1, [200, 404], [1, 2]],
    "the re-send spends its own tries, not the call's" => [2, [200, 503, 503], [1, 2, 3]],
]);

test('a request sent again after its call settled starts from attempt 1 and reports its own failure', function (Closure $first, int $status, int $failed): void {
    $attempts = [];
    $failures = 0;
    $request = friendListRequest();
    $connector = fakedConnector(new MockClient([MockResponse::make('{"friendslist":{"friends":[]}}', $status), MockResponse::make([], 404)]))
        ->onRequest(static function (RequestSending $sending) use (&$attempts): void {
            $attempts[] = $sending->attempt;
        })
        ->onFailure(static function () use (&$failures): void {
            $failures++;
        });

    try {
        $first($connector, $request);
    } catch (Throwable) {
        // pool() throws whichever way the first call went.
    }

    expect(fn (): Response => $connector->send($request))->toThrow(SteamApiException::class)
        ->and($attempts)->toBe([1, 1])
        ->and($failures)->toBe($failed);
})->with(everyWayToSend())->with([
    'after a success' => [200, 1],
    'after a failure' => [404, 2],
]);

test('a sendAsync() never settled leaves a later send() of the request a call of its own', function (): void {
    $attempts = [];
    $failures = 0;
    $request = friendListRequest();
    $connector = new SteamConnector(new SteamConfig('test-key', sender: new class implements Sender
    {
        public function getFactoryCollection(): FactoryCollection
        {
            return senderAnswering([])->getFactoryCollection();
        }

        public function send(PendingRequest $pendingRequest): Response
        {
            throw new LogicException('Only sendAsync() is expected.');
        }

        public function sendAsync(PendingRequest $pendingRequest): PromiseInterface
        {
            return new Promise;
        }
    }))
        ->onRequest(static function (RequestSending $sending) use (&$attempts): void {
            $attempts[] = $sending->attempt;
        })
        ->onFailure(static function () use (&$failures): void {
            $failures++;
        });

    $connector->sendAsync($request);
    Utils::queue()->run();

    $mockClient = new MockClient([MockResponse::make([], 404)]);

    expect(fn (): Response => $connector->send($request, $mockClient))->toThrow(SteamApiException::class)
        ->and($attempts)->toBe([1, 1])
        ->and($failures)->toBe(1)
        ->and($mockClient->getRecordedResponses())->toHaveCount(1);
});

test('a 429 on a custom request locks, counts and reaches onResponse however it was sent', function (Closure $send, Closure $answering, Closure $request): void {
    $statuses = [];
    $connector = $answering()->onResponse(static function (ResponseReceived $received) use (&$statuses): void {
        $statuses[] = $received->status;
    });

    expect(failureFrom(fn (): mixed => $send($connector, $request())))->toBeInstanceOf(SteamRateLimitException::class)
        ->and(failureFrom(fn (): mixed => $send($connector, $request())))->toBeInstanceOf(SteamRateLimitException::class)
        ->and($statuses)->toBe([429])
        ->and(dailyHits($connector))->toBe(1);
})->with(everyWayToSend())->with([
    'MockClient' => [static fn (): SteamConnector => fakedConnector(new MockClient([MockResponse::make([], 429), MockResponse::make([])]))],
    'Guzzle handler' => [static fn (): SteamConnector => connectorAnswering([new PsrResponse(429), new PsrResponse(200, [], '[]')])],
])->with([
    'raising its own exception' => [requestRaisingItsOwnException(...)],
    'with its own response class' => [static fn (): Request => new class extends Request
    {
        protected Method $method = Method::GET;

        protected ?string $response = Response::class;

        public function resolveEndpoint(): string
        {
            return '/ISteamDirectory/GetCMList/v1/';
        }
    }],
]);

function sendCurrentPlayersRequest(): SteamConnector
{
    $connector = new SteamConnector(new SteamConfig);

    $connector->withMockClient(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::fixture('ISteamUserStats/GetNumberOfCurrentPlayers/default'),
    ]));

    $connector->send(new GetNumberOfCurrentPlayersRequest(381210));

    return $connector;
}

/**
 * @return list<string>
 */
function dailyLimitKeys(): array
{
    return array_values(array_filter(
        array_keys((new MemoryStore)->getStore()),
        static fn (string $key): bool => str_ends_with($key, '100000_every_utc_midnight'),
    ));
}
