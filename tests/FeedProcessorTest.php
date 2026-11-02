<?php

declare(strict_types=1);

namespace Tests;

use App\Feed;
use App\FeedProcessor;
use App\FirestoreRepository;
use App\LineNotifier;
use App\RaindropNotifier;
use App\RssParser;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class TestableFeedProcessor extends FeedProcessor
{
    private ?\DateTime $mockedTime = null;

    public function setMockedTime(\DateTime $time): void
    {
        $this->mockedTime = $time;
    }

    protected function getCurrentTime(): \DateTime
    {
        return $this->mockedTime ?? parent::getCurrentTime();
    }
}

class FeedProcessorTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new Logger('test');
        $this->logger->pushHandler(new NullHandler());
    }

    public function test_無効なフィードはスキップされる(): void
    {
        $feed = new Feed('feed-1', '無効フィード', 'https://example.com/rss', 'LINE', 'bot1', false);

        $repo = $this->createMock(FirestoreRepository::class);
        $repo->method('getRssFeeds')->willReturn([$feed]);
        $repo->expects($this->never())->method('getLastUpdatedAt');

        $rssParser = $this->createMock(RssParser::class);
        $rssParser->expects($this->never())->method('parse');

        $lineNotifier = $this->createMock(LineNotifier::class);
        $raindropNotifier = $this->createMock(RaindropNotifier::class);

        $processor = new FeedProcessor($repo, $rssParser, $lineNotifier, $raindropNotifier, $this->logger);
        $processor->processAllFeeds();
    }

    public function test_初回実行時は通知がスキップされ最終更新日時が保存される(): void
    {
        $feed = new Feed('feed-1', '初回フィード', 'https://example.com/rss', 'LINE', 'bot1', true);

        $repo = $this->createMock(FirestoreRepository::class);
        $repo->method('getRssFeeds')->willReturn([$feed]);
        $repo->method('getLastUpdatedAt')->with('feed-1')->willReturn(null);
        $repo->expects($this->once())->method('saveLastUpdatedAt')->with('feed-1', 1700000100);

        $rssParser = $this->createMock(RssParser::class);
        $rssParser->method('parse')->with('https://example.com/rss')->willReturn([
            ['title' => '記事1', 'link' => 'https://example.com/1', 'description' => 'desc', 'updated_at' => 1700000100],
        ]);

        $lineNotifier = $this->createMock(LineNotifier::class);
        $lineNotifier->expects($this->never())->method('notify');

        $raindropNotifier = $this->createMock(RaindropNotifier::class);

        $processor = new FeedProcessor($repo, $rssParser, $lineNotifier, $raindropNotifier, $this->logger);
        $processor->processAllFeeds();
    }

    public function test_新規記事が20件を超える場合は通知がスキップされる(): void
    {
        $feed = new Feed('feed-1', '大量フィード', 'https://example.com/rss', 'LINE', 'bot1', true);

        $repo = $this->createMock(FirestoreRepository::class);
        $repo->method('getRssFeeds')->willReturn([$feed]);
        $repo->method('getLastUpdatedAt')->with('feed-1')->willReturn(1700000000);
        $repo->expects($this->once())->method('saveLastUpdatedAt');

        $items = [];
        for ($i = 1; $i <= 21; $i++) {
            $items[] = [
                'title' => "記事{$i}",
                'link' => "https://example.com/{$i}",
                'description' => "desc{$i}",
                'updated_at' => 1700000000 + $i,
            ];
        }

        $rssParser = $this->createMock(RssParser::class);
        $rssParser->method('parse')->willReturn($items);

        $lineNotifier = $this->createMock(LineNotifier::class);
        $lineNotifier->expects($this->never())->method('notify');

        $raindropNotifier = $this->createMock(RaindropNotifier::class);

        $processor = new FeedProcessor($repo, $rssParser, $lineNotifier, $raindropNotifier, $this->logger);
        $processor->processAllFeeds();
    }

    public function test_LINE通知が夜間時間外はスキップされる(): void
    {
        $feed = new Feed('feed-1', 'LINEフィード', 'https://example.com/rss', 'LINE', 'test_bot', true);

        $repo = $this->createMock(FirestoreRepository::class);
        $repo->method('getRssFeeds')->willReturn([$feed]);
        $repo->method('getLastUpdatedAt')->with('feed-1')->willReturn(1700000000);

        $rssParser = $this->createMock(RssParser::class);
        $rssParser->method('parse')->willReturn([
            ['title' => '記事1', 'link' => 'https://example.com/1', 'description' => 'desc', 'updated_at' => 1700000010],
        ]);

        $lineNotifier = $this->createMock(LineNotifier::class);
        $lineNotifier->expects($this->never())->method('notify');

        $raindropNotifier = $this->createMock(RaindropNotifier::class);

        $processor = new TestableFeedProcessor($repo, $rssParser, $lineNotifier, $raindropNotifier, $this->logger);
        // JST 午前5時（通知対象外時間: 7〜22時以外）
        $processor->setMockedTime(new \DateTime('2023-01-01 05:00:00', new \DateTimeZone('Asia/Tokyo')));
        $processor->processAllFeeds();
    }

    public function test_LINE通知が日中時間内に正常に送信される(): void
    {
        $feed = new Feed('feed-1', 'LINEフィード', 'https://example.com/rss', 'LINE', 'test_bot', true);

        $repo = $this->createMock(FirestoreRepository::class);
        $repo->method('getRssFeeds')->willReturn([$feed]);
        $repo->method('getLastUpdatedAt')->with('feed-1')->willReturn(1700000000);

        $item = ['title' => '記事1', 'link' => 'https://example.com/1', 'description' => 'desc', 'updated_at' => 1700000010];
        $rssParser = $this->createMock(RssParser::class);
        $rssParser->method('parse')->willReturn([$item]);

        $lineNotifier = $this->createMock(LineNotifier::class);
        $lineNotifier->expects($this->once())
            ->method('notify')
            ->with('dummy_token', 'dummy_target', 'LINEフィード', $item)
            ->willReturn(true);

        $raindropNotifier = $this->createMock(RaindropNotifier::class);

        $processor = new TestableFeedProcessor($repo, $rssParser, $lineNotifier, $raindropNotifier, $this->logger);
        // JST 12:00（通知時間内）
        $processor->setMockedTime(new \DateTime('2023-01-01 12:00:00', new \DateTimeZone('Asia/Tokyo')));
        $processor->processAllFeeds();
    }

    public function test_Save通知がRaindropに正常に送信される(): void
    {
        $feed = new Feed('feed-1', 'Raindropフィード', 'https://example.com/rss', 'Save', null, true);

        $repo = $this->createMock(FirestoreRepository::class);
        $repo->method('getRssFeeds')->willReturn([$feed]);
        $repo->method('getLastUpdatedAt')->with('feed-1')->willReturn(1700000000);

        $item = ['title' => '記事1', 'link' => 'https://example.com/1', 'description' => 'desc', 'updated_at' => 1700000010];
        $rssParser = $this->createMock(RssParser::class);
        $rssParser->method('parse')->willReturn([$item]);

        $lineNotifier = $this->createMock(LineNotifier::class);
        $raindropNotifier = $this->createMock(RaindropNotifier::class);
        $raindropNotifier->expects($this->once())
            ->method('save')
            ->with('test-raindrop-key', $item)
            ->willReturn(true);

        $processor = new FeedProcessor($repo, $rssParser, $lineNotifier, $raindropNotifier, $this->logger);
        $processor->processAllFeeds();
    }
}
