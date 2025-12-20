<?php

declare(strict_types=1);

namespace Tests;

use App\Feed;
use App\FeedProcessor;
use App\FirestoreRepository;
use App\LineNotifier;
use App\RaindropNotifier;
use App\RssParser;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class FeedProcessorTest extends TestCase
{
    private $firestoreRepoMock;
    private $rssParserMock;
    private $lineNotifierMock;
    private $raindropNotifierMock;
    private $loggerMock;

    protected function setUp(): void
    {
        $this->firestoreRepoMock = $this->createMock(FirestoreRepository::class);
        $this->rssParserMock = $this->createMock(RssParser::class);
        $this->lineNotifierMock = $this->createMock(LineNotifier::class);
        $this->raindropNotifierMock = $this->createMock(RaindropNotifier::class);
        $this->loggerMock = $this->createMock(Logger::class);
    }

    private function createFeedProcessorWithFixedTime(string $dateTime): FeedProcessor
    {
        $fixedTime = new \DateTime($dateTime, new \DateTimeZone('Asia/Tokyo'));

        // FeedProcessorを継承した匿名クラスを作成し、getCurrentTimeをオーバーライド
        $feedProcessor = new class($this->firestoreRepoMock, $this->rssParserMock, $this->lineNotifierMock, $this->raindropNotifierMock, $this->loggerMock) extends FeedProcessor {
            private $fixedTime;
            public function setFixedTime(\DateTime $time)
            {
                $this->fixedTime = $time;
            }
            protected function getCurrentTime(): \DateTime
            {
                return $this->fixedTime;
            }
        };

        $feedProcessor->setFixedTime($fixedTime);
        return $feedProcessor;
    }

    public function test_LINE通知が許可された時間帯に通知が実行される(): void
    {
        $feed = new Feed('test_id', 'Test Feed', 'https://example.com/rss', 'LINE', 'test_bot');
        $this->firestoreRepoMock->method('getRssFeeds')->willReturn([$feed]);
        $this->firestoreRepoMock->method('getLastUpdatedAt')->willReturn(time() - 3600);
        $this->rssParserMock->method('parse')->willReturn([
            ['title' => 'New Post', 'link' => 'https://example.com/post1', 'updated_at' => time()]
        ]);

        // 環境変数を設定
        putenv('LINE_TOKENS_N_TARGETS={"tokens":{"test_bot":"test_token"},"target_ids":{"test_bot":"test_target"}}');

        $this->lineNotifierMock->expects($this->once())->method('notify')->willReturn(true);

        // 日本時間で10時（許可された時間帯）に設定
        $feedProcessor = $this->createFeedProcessorWithFixedTime('2023-01-01 10:00:00');
        $feedProcessor->processAllFeeds();
    }

    public function test_LINE通知が許可されていない時間帯に通知がスキップされる(): void
    {
        $feed = new Feed('test_id', 'Test Feed', 'https://example.com/rss', 'LINE', 'test_bot');
        $this->firestoreRepoMock->method('getRssFeeds')->willReturn([$feed]);
        $this->firestoreRepoMock->method('getLastUpdatedAt')->willReturn(time() - 3600);
        $this->rssParserMock->method('parse')->willReturn([
            ['title' => 'New Post', 'link' => 'https://example.com/post1', 'updated_at' => time()]
        ]);

        $this->lineNotifierMock->expects($this->never())->method('notify');

        // 日本時間で3時（許可されていない時間帯）に設定
        $feedProcessor = $this->createFeedProcessorWithFixedTime('2023-01-01 03:00:00');
        $feedProcessor->processAllFeeds();
    }

    public function test_Raindropへの保存処理が正しく呼び出される(): void
    {
        $feed = new Feed('test_id', 'Test Feed', 'https://example.com/rss', 'Save', null);
        $this->firestoreRepoMock->method('getRssFeeds')->willReturn([$feed]);
        $this->firestoreRepoMock->method('getLastUpdatedAt')->willReturn(time() - 3600);
        $this->rssParserMock->method('parse')->willReturn([
            ['title' => 'New Post', 'link' => 'https://example.com/post1', 'updated_at' => time()]
        ]);

        // 環境変数を設定
        putenv('RAINDROP_KEY=test_raindrop_key');

        $this->raindropNotifierMock->expects($this->once())->method('save')->willReturn(true);
        $this->lineNotifierMock->expects($this->never())->method('notify');

        $feedProcessor = new FeedProcessor(
            $this->firestoreRepoMock,
            $this->rssParserMock,
            $this->lineNotifierMock,
            $this->raindropNotifierMock,
            $this->loggerMock
        );
        $feedProcessor->processAllFeeds();
    }
}
