<?php

declare(strict_types=1);

use Fkrzski\SteamApiSdk\Dto\Badge;
use Fkrzski\SteamApiSdk\Dto\Friend;
use Fkrzski\SteamApiSdk\Dto\NewsItem;
use Fkrzski\SteamApiSdk\Dto\PlayerAchievement;
use Fkrzski\SteamApiSdk\Dto\PlayerSummary;
use Fkrzski\SteamApiSdk\Http\Requests\ISteamWebAPIUtil\GetServerInfoRequest;
use Fkrzski\SteamApiSdk\SteamConfig;
use Fkrzski\SteamApiSdk\SteamConnector;
use Fkrzski\SteamApiSdk\Testing\Factories\IPlayerService\BadgeFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamNews\NewsItemFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\FriendFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUser\PlayerSummaryFactory;
use Fkrzski\SteamApiSdk\Testing\Factories\ISteamUserStats\PlayerAchievementFactory;
use Fkrzski\SteamApiSdk\Testing\SteamResponse;
use Saloon\Http\Faking\MockClient;

covers([Badge::class, Friend::class, NewsItem::class, PlayerAchievement::class, PlayerSummary::class, GetServerInfoRequest::class]);

beforeEach(function (): void {
    $this->timezone = date_default_timezone_get();
    date_default_timezone_set('Europe/Warsaw');
});

afterEach(function (): void {
    date_default_timezone_set($this->timezone);
});

test('a timestamp comes back in UTC whatever date.timezone says', function (Closure $read): void {
    $moment = new DateTimeImmutable('@1600000000');

    expect($read($moment)->format(DATE_ATOM))->toBe('2020-09-13T12:26:40+00:00');
})->with([
    'friend since' => [static fn (DateTimeImmutable $moment): DateTimeImmutable => FriendFactory::new()->since($moment)->make()->friendSince],
    'news published at' => [static fn (DateTimeImmutable $moment): DateTimeImmutable => NewsItemFactory::new()->publishedAt($moment)->make()->publishedAt],
    'summary time created' => [static fn (DateTimeImmutable $moment): ?DateTimeImmutable => PlayerSummaryFactory::new()->state(['timecreated' => $moment->getTimestamp()])->make()->timeCreated],
    'summary last log off' => [static fn (DateTimeImmutable $moment): ?DateTimeImmutable => PlayerSummaryFactory::new()->state(['lastlogoff' => $moment->getTimestamp()])->make()->lastLogOff],
    'badge completed at' => [static fn (DateTimeImmutable $moment): DateTimeImmutable => BadgeFactory::new()->completedAt($moment)->make()->completedAt],
    'achievement unlocked at' => [static fn (DateTimeImmutable $moment): ?DateTimeImmutable => PlayerAchievementFactory::new()->unlocked($moment)->make()->unlockedAt],
    'server time' => [static function (DateTimeImmutable $moment): DateTimeImmutable {
        $connector = new SteamConnector(new SteamConfig);
        $connector->withMockClient(new MockClient([GetServerInfoRequest::class => SteamResponse::serverInfo($moment)]));

        return $connector->webApi()->serverTime();
    }],
]);
