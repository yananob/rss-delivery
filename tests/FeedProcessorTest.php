<?php

declare(strict_types=1);

namespace Tests;

use App\Feed;
use App\FeedProcessor;
use App\FirestoreRepository;
use App\LineNotifier;
use App\RaindropNotifier;
use App\RssParser;
use DateTime;
use DateTimeZone;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

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
        $repoMock = $this->createMock(FirestoreRepository::class);
        $disabledFeed = new Feed('f1', 'Disabled Feed', 'https://example.com/rss', 'LINE', 'bot1', false);
        $repoMock->expects($this->once())->method('getRssFeeds')->willReturn([$disabledFeed]);
        $repoMock->expects($this->never())->method('getLastUpdatedAt');

        $rssParserMock = $this->createMock(RssParser::class);
        $lineNotifierMock = $this->createMock(LineNotifier::class);
        $raindropNotifierMock = $this->createMock(RaindropNotifier::class);

        $processor = new FeedProcessor($repoMock, $rssParserMock, $lineNotifierMock, $raindropNotifierMock, $this->logger);
        $processor->processAllFeeds();
    }

    public function test_初回実行時はタイムスタンプを保存し通知はスキップされる(): void
    {
        $repoMock = $this->createMock(FirestoreRepository::class);
        $feed = new Feed('f1', 'Test Feed', 'https://example.com/rss', 'LINE', 'bot1', true);
        $repoMock->expects($this->once())->method('getRssFeeds')->willReturn([$feed]);
        $repoMock->expects($this->once())->method('getLastUpdatedAt')->with('f1')->willReturn(null);
        $repoMock->expects($this->once())->method('saveLastUpdatedAt')->with('f1', 1000);

        $rssParserMock = $this->createMock(RssParser::class);
        $rssParserMock->expects($this->once())->method('parse')->with('https://example.com/rss')->willReturn([
            ['title' => 'Item 1', 'updated_at' => 1000, 'link' => 'https://example.com/1']
        ]);

        $lineNotifierMock = $this->createMock(LineNotifier::class);
        $lineNotifierMock->expects($this->never())->method('notify');

        $raindropNotifierMock = $this->createMock(RaindropNotifier::class);

        $processor = new FeedProcessor($repoMock, $rssParserMock, $lineNotifierMock, $raindropNotifierMock, $this->logger);
        $processor->processAllFeeds();
    }

    public function test_新規アイテムが20件超の場合は通知をスキップする(): void
    {
        $repoMock = $this->createMock(FirestoreRepository::class);
        $feed = new Feed('f1', 'Flood Feed', 'https://example.com/rss', 'LINE', 'bot1', true);
        $repoMock->expects($this->once())->method('getRssFeeds')->willReturn([$feed]);
        $repoMock->expects($this->once())->method('getLastUpdatedAt')->with('f1')->willReturn(100);
        $repoMock->expects($this->once())->method('saveLastUpdatedAt')->with('f1', 125);

        $items = [];
        for ($i = 1; $i <= 25; $i++) {
            $items[] = ['title' => "Item $i", 'updated_at' => 100 + $i, 'link' => "https://example.com/$i"];
        }

        $rssParserMock = $this->createMock(RssParser::class);
        $rssParserMock->expects($this->once())->method('parse')->willReturn($items);

        $lineNotifierMock = $this->createMock(LineNotifier::class);
        $lineNotifierMock->expects($this->never())->method('notify');

        $raindropNotifierMock = $this->createMock(RaindropNotifier::class);

        $processor = new FeedProcessor($repoMock, $rssParserMock, $lineNotifierMock, $raindropNotifierMock, $this->logger);
        $processor->processAllFeeds();
    }

    public function test_JST夜間時間帯はLINE通知をスキップする(): void
    {
        $repoMock = $this->createMock(FirestoreRepository::class);
        $feed = new Feed('f1', 'Night Feed', 'https://example.com/rss', 'LINE', 'bot1', true);
        $repoMock->expects($this->once())->method('getRssFeeds')->willReturn([$feed]);
        $repoMock->expects($this->once())->method('getLastUpdatedAt')->with('f1')->willReturn(100);

        $rssParserMock = $this->createMock(RssParser::class);
        $rssParserMock->expects($this->once())->method('parse')->willReturn([
            ['title' => 'Night Item', 'updated_at' => 200, 'link' => 'https://example.com/night']
        ]);

        $lineNotifierMock = $this->createMock(LineNotifier::class);
        $lineNotifierMock->expects($this->never())->method('notify');

        $raindropNotifierMock = $this->createMock(RaindropNotifier::class);

        // 23:00 JSTを返すテスト用サブクラス
        $processor = new class($repoMock, $rssParserMock, $lineNotifierMock, $raindropNotifierMock, $this->logger) extends FeedProcessor {
            protected function getCurrentTime(): DateTime
            {
                return new DateTime('2023-01-01 23:00:00', new DateTimeZone('Asia/Tokyo'));
            }
        };

        $processor->processAllFeeds();
    }

    public function test_新着記事の通知処理が正しく実行される(): void
    {
        $originalEnv = getenv('LINE_TOKENS_N_TARGETS');
        $originalRaindrop = getenv('RAINDROP_KEY');

        putenv('LINE_TOKENS_N_TARGETS=' . json_encode([
            'tokens' => ['bot1' => 'token1'],
            'target_ids' => ['bot1' => 'target1'],
        ]));
        putenv('RAINDROP_KEY=raindrop_secret');

        $repoMock = $this->createMock(FirestoreRepository::class);
        $lineFeed = new Feed('f1', 'Line Feed', 'https://example.com/line', 'LINE', 'bot1', true);
        $saveFeed = new Feed('f2', 'Save Feed', 'https://example.com/save', 'Save', null, true);

        $repoMock->expects($this->once())->method('getRssFeeds')->willReturn([$lineFeed, $saveFeed]);
        $repoMock->method('getLastUpdatedAt')->willReturnMap([
            ['f1', 100],
            ['f2', 100],
        ]);

        $rssParserMock = $this->createMock(RssParser::class);
        $rssParserMock->method('parse')->willReturnMap([
            ['https://example.com/line', [['title' => 'Line Item', 'updated_at' => 200, 'link' => 'https://example.com/l1']]],
            ['https://example.com/save', [['title' => 'Save Item', 'updated_at' => 200, 'link' => 'https://example.com/s1']]],
        ]);

        $lineNotifierMock = $this->createMock(LineNotifier::class);
        $lineNotifierMock->expects($this->once())
            ->method('notify')
            ->with('token1', 'target1', 'Line Feed', ['title' => 'Line Item', 'updated_at' => 200, 'link' => 'https://example.com/l1'])
            ->willReturn(true);

        $raindropNotifierMock = $this->createMock(RaindropNotifier::class);
        $raindropNotifierMock->expects($this->once())
            ->method('save')
            ->with('raindrop_secret', ['title' => 'Save Item', 'updated_at' => 200, 'link' => 'https://example.com/s1'])
            ->willReturn(true);

        $processor = new class($repoMock, $rssParserMock, $lineNotifierMock, $raindropNotifierMock, $this->logger) extends FeedProcessor {
            protected function getCurrentTime(): DateTime
            {
                return new DateTime('2023-01-01 12:00:00', new DateTimeZone('Asia/Tokyo'));
            }
        };

        $processor->processAllFeeds();

        // Restore environment variables
        putenv($originalEnv !== false ? "LINE_TOKENS_N_TARGETS=$originalEnv" : 'LINE_TOKENS_N_TARGETS');
        putenv($originalRaindrop !== false ? "RAINDROP_KEY=$originalRaindrop" : 'RAINDROP_KEY');
    }
}
