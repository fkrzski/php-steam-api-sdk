<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Exceptions\ApiKeyNotConfiguredException;
use Fkrzski\SteamApiSdk\Exceptions\SteamApiException;
use Fkrzski\SteamApiSdk\Exceptions\SteamConnectionException;
use Fkrzski\SteamApiSdk\Exceptions\SteamRateLimitException;
use Fkrzski\SteamApiSdk\Hooks\RequestSending;
use Fkrzski\SteamApiSdk\Hooks\ResponseReceived;
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

covers([SteamRateLimitException::class, SteamConnector::class, ApiKeyNotConfiguredException::class, SteamApiResponse::class]);

test('connector exposes Steam daily limit of 100k requests', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));

    $limits = $connector->getLimits();
    $primary = $limits[0];

    expect($primary)->toBeInstanceOf(Limit::class)
        ->and($primary->getAllow())->toBe(100_000)
        ->and($primary->getName())->toContain('SteamConnector')
        ->and($primary->getName())->toContain('100000');
});

test('connector defaults to a MemoryStore when none is configured', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));

    expect($connector->rateLimitStore())->toBeInstanceOf(MemoryStore::class);
});

test('connector honours the configured rate limit store', function (): void {
    $store = new MemoryStore;
    $connector = new SteamConnector(new SteamConfig('test-key', $store));

    expect($connector->rateLimitStore())->toBe($store);
});

test('limits are keyed by a hash of the API key', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'));

    $names = array_map(
        static fn (Limit $limit): string => $limit->getName(),
        $connector->getLimits(),
    );

    expect($names)->toBe([
        'SteamConnector:62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef:100000_every_utc_midnight',
        'SteamConnector:62af8704764faf8ea82fc61ce9c4c3908b6cb97d463a634e9e587d7c885db0ef:too_many_attempts_limit',
    ])->and(implode('', $names))->not->toContain('test-key');
});

test('the daily budget resets at the next midnight UTC whatever date.timezone says', function (string $timezone): void {
    $default = date_default_timezone_get();
    date_default_timezone_set($timezone);

    try {
        $connector = sendVanityUrlRequest('test-key');
    } finally {
        date_default_timezone_set($default);
    }

    $expiry = $connector->getLimits()[0]->update($connector->rateLimitStore())->getExpiryTimestamp();

    expect($expiry % 86_400)->toBe(0)
        ->and($expiry - time())->toBeGreaterThan(0)->toBeLessThanOrEqual(86_400);
})->with(['UTC', 'Pacific/Kiritimati', 'Pacific/Pago_Pago']);

test('the daily budget still resets at midnight UTC when a second passes after the limit is resolved', function (): void {
    $limit = new SteamConnector(new SteamConfig('test-key'))->getLimits()[0];

    time_sleep_until(time() + 1);

    expect($limit->getExpiryTimestamp() % 86_400)->toBe(0);
});

test('a spent budget is refused until midnight UTC', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key'))->withMockClient(new MockClient([
        MockResponse::make('{"friendslist":{"friends":[]}}'),
    ]));

    $connector->getLimits()[0]->hit(100_000)->save($connector->rateLimitStore());

    try {
        $connector->send(friendListRequest());
        $this->fail('Expected SteamRateLimitException was not thrown.');
    } catch (SteamRateLimitException $steamRateLimitException) {
        expect($steamRateLimitException->limit->getExpiryTimestamp() % 86_400)->toBe(0)
            ->and($steamRateLimitException->limit->getRemainingSeconds())->toBeGreaterThan(0)->toBeLessThanOrEqual(86_400);
    }
});

test('each API key gets its own counter', function (): void {
    $first = sendVanityUrlRequest('key-aaa');
    $second = sendVanityUrlRequest('key-bbb');

    expect(dailyLimitKeys())->toHaveCount(2)
        ->and(dailyHits($first))->toBe(1)
        ->and(dailyHits($second))->toBe(1);
});

test('connectors sharing an API key share one counter', function (): void {
    sendVanityUrlRequest('key-aaa');
    $second = sendVanityUrlRequest('key-aaa');

    expect(dailyLimitKeys())->toHaveCount(1)
        ->and(dailyHits($second))->toBe(2);
});

test('without an API key the daily budget is not metered at all', function (): void {
    $connector = new SteamConnector(new SteamConfig);

    $names = array_map(
        static fn (Limit $limit): string => $limit->getName(),
        $connector->getLimits(),
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

test('a 429 without Retry-After late in the cool-down window locks for the whole cool-down', function (): void {
    $connector = connectorAnswering([new PsrResponse(429)], new SteamConfig('test-key', tooManyRequestsCooldown: 300));

    array_last($connector->getLimits())->setExpiryTimestamp(time() + 5)->save($connector->rateLimitStore());

    $before = time();
    $throttled = rateLimitOf(fn (): Response => $connector->send(friendListRequest()));
    $after = time();

    expect($throttled->limit->getExpiryTimestamp())->toBeGreaterThanOrEqual($before + 300)->toBeLessThanOrEqual($after + 300);
});

test('a 429 refuses requests for as long as Retry-After says, or for the cool-down without one', function (SteamConfig $config, array $headers, int $seconds): void {
    $connector = connectorAnswering([new PsrResponse(429, $headers)], $config);

    $before = time();
    $throttled = rateLimitOf(fn (): Response => $connector->send(friendListRequest()));
    $after = time();

    expect($throttled->limit->getExpiryTimestamp())->toBeGreaterThanOrEqual($before + $seconds)->toBeLessThanOrEqual($after + $seconds);
})->with([
    'default cool-down' => [new SteamConfig('test-key'), [], 60],
    'Retry-After under the cool-down' => [new SteamConfig('test-key', tooManyRequestsCooldown: 300), ['Retry-After' => '120'], 120],
    'Retry-After over the cool-down' => [new SteamConfig('test-key', tooManyRequestsCooldown: 30), ['Retry-After' => '120'], 120],
]);

test('a Retry-After of 0 or in the past locks for one second, not the cool-down', function (Closure $retryAfter): void {
    $connector = connectorAnswering([new PsrResponse(429, ['Retry-After' => $retryAfter()])], new SteamConfig('test-key', tooManyRequestsCooldown: 600));

    $before = time();
    $throttled = rateLimitOf(fn (): Response => $connector->send(friendListRequest()));
    $after = time();

    expect($throttled->limit->getExpiryTimestamp())->toBeGreaterThanOrEqual($before + 1)->toBeLessThanOrEqual($after + 1);
})->with([
    'zero' => [static fn (): string => '0'],
    'negative' => [static fn (): string => '-5'],
    'a date in the past' => [static fn (): string => gmdate('D, d M Y H:i:s \G\M\T', time() - 30)],
]);

test('the cool-down holds with a key or without and whatever the rate limits', function (SteamConfig $config): void {
    $limiter = array_last(new SteamConnector($config)->getLimits());

    expect($limiter->getName())->toEndWith(':too_many_attempts_limit')
        ->and($limiter->getReleaseInSeconds())->toBe(300);
})->with([
    'without a key' => [new SteamConfig(tooManyRequestsCooldown: 300)],
    'with a key' => [new SteamConfig('test-key', tooManyRequestsCooldown: 300)],
    'no local limits' => [new SteamConfig('test-key', rateLimits: [], tooManyRequestsCooldown: 300)],
    'own limits' => [new SteamConfig('test-key', rateLimits: [Limit::allow(10)->everyMinute()], tooManyRequestsCooldown: 300)],
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

test('hitting the limit throws SteamRateLimitException with the offending limit', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key', rateLimits: [Limit::allow(3)->everyMinute()]));

    $mock = new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]);

    $connector->withMockClient($mock);

    $connector->send(new ResolveVanityUrlRequest('first'));
    $connector->send(new ResolveVanityUrlRequest('second'));
    $connector->send(new ResolveVanityUrlRequest('third'));

    try {
        $connector->send(new ResolveVanityUrlRequest('fourth'));
        $this->fail('Expected SteamRateLimitException was not thrown.');
    } catch (SteamRateLimitException $steamRateLimitException) {
        expect($steamRateLimitException->limit)->toBeInstanceOf(Limit::class)
            ->and($steamRateLimitException->limit->getAllow())->toBe(3)
            ->and($steamRateLimitException->getMessage())->toContain('Steam API rate limit reached');
    }
});

test('configured limits replace the daily budget', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key', rateLimits: [Limit::allow(10)->everyMinute()]));
    $prefix = 'SteamConnector:'.hash('sha256', 'test-key');

    $names = array_map(
        static fn (Limit $limit): string => $limit->getName(),
        $connector->getLimits(),
    );

    expect($names)->toBe([$prefix.':10_every_60', $prefix.':too_many_attempts_limit']);
});

test('an empty list of limits meters nothing locally', function (): void {
    $connector = new SteamConnector(new SteamConfig('test-key', rateLimits: []));

    $connector->withMockClient(new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]));

    $connector->send(new ResolveVanityUrlRequest('first'));
    $connector->send(new ResolveVanityUrlRequest('second'));

    expect(array_keys((new MemoryStore)->getStore()))
        ->toBe(['SteamConnector:'.hash('sha256', 'test-key').':too_many_attempts_limit']);
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

test('configured limits apply without an API key', function (): void {
    $connector = new SteamConnector(new SteamConfig(rateLimits: [Limit::allow(1)->everyMinute()]));

    $connector->withMockClient(new MockClient([
        GetNumberOfCurrentPlayersRequest::class => MockResponse::fixture('ISteamUserStats/GetNumberOfCurrentPlayers/default'),
    ]));

    $connector->send(new GetNumberOfCurrentPlayersRequest(381210));

    expect(fn (): Response => $connector->send(new GetNumberOfCurrentPlayersRequest(381210)))
        ->toThrow(function (SteamRateLimitException $steamRateLimitException): void {
            expect($steamRateLimitException->limit->getName())->toBe('SteamConnector:anonymous:1_every_60');
        });
});

test('configured limits keep a counter per API key', function (): void {
    $rateLimits = [Limit::allow(1)->everyMinute()];
    $mockClient = new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]);

    $first = new SteamConnector(new SteamConfig('key-aaa', rateLimits: $rateLimits))->withMockClient($mockClient);
    $second = new SteamConnector(new SteamConfig('key-bbb', rateLimits: $rateLimits))->withMockClient($mockClient);

    $first->send(new ResolveVanityUrlRequest('first'));

    expect(fn (): Response => $first->send(new ResolveVanityUrlRequest('again')))
        ->toThrow(SteamRateLimitException::class)
        ->and($second->send(new ResolveVanityUrlRequest('second'))->status())->toBe(200);
});

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
    Config::sleepUsing(static function (): void {});

    try {
        $answered = 0;
        $attempts = [];
        $failures = [];
        $connector = new SteamConnector(new SteamConfig('test-key', tries: $tries, rateLimits: [
            Limit::custom(static function (Response $response, Limit $limit) use (&$answered): void {
                if ($answered++ === 0) {
                    $limit->exceeded(1);
                }
            })->sleep(),
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
    } finally {
        Config::sleepUsing(null);
    }
})->with([
    'send' => [static fn (SteamConnector $connector, MockClient $mockClient): mixed => $connector->withMockClient($mockClient)->send(friendListRequest())],
    'send with its own mock client' => [static fn (SteamConnector $connector, MockClient $mockClient): mixed => $connector->send(friendListRequest(), $mockClient)],
    'sendAsync with its own mock client' => [static fn (SteamConnector $connector, MockClient $mockClient): mixed => $connector->sendAsync(friendListRequest(), $mockClient)->wait()],
    'pool' => [static fn (SteamConnector $connector, MockClient $mockClient): mixed => everyWayToSend()['pool'][0]($connector->withMockClient($mockClient))],
])->with([
    'the re-send fails' => [1, [200, 404], [1, 2]],
    "the re-send spends its own tries, not the call's" => [2, [200, 503, 503], [1, 2, 3]],
])->skip(! method_exists(Config::class, 'sleepUsing'), 'Saloon before 4.4 has no sleep handler, so the test would sleep for real.');

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

function sendVanityUrlRequest(string $apiKey): SteamConnector
{
    $connector = new SteamConnector(new SteamConfig($apiKey));

    $connector->withMockClient(new MockClient([
        ResolveVanityUrlRequest::class => MockResponse::fixture('ISteamUser/ResolveVanityUrl/success'),
    ]));

    $connector->send(new ResolveVanityUrlRequest('nick'));

    return $connector;
}

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

function rateLimitOf(Closure $call): SteamRateLimitException
{
    try {
        $call();
    } catch (SteamRateLimitException $steamRateLimitException) {
        return $steamRateLimitException;
    }

    throw new RuntimeException('Expected the request to be rate limited.');
}
