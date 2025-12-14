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
    private FeedProcessor $feedProcessor;

    protected function setUp(): void
    {
        $this->firestoreRepoMock = $this->createMock(FirestoreRepository::class);
        $this->rssParserMock = $this->createMock(RssParser::class);
        $this->lineNotifierMock = $this->createMock(LineNotifier::class);
        $this->raindropNotifierMock = $this->createMock(RaindropNotifier::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->feedProcessor = new FeedProcessor(
            $this->firestoreRepoMock,
            $this->rssParserMock,
            $this->lineNotifierMock,
            $this->raindropNotifierMock,
            $this->loggerMock
        );
    }

    public function testProcessAllFeeds_LineNotification()
    {
        $feeds = [
            new Feed('feed1', 'Test Feed 1', 'http://example.com/rss1', 'LINE', 'bot1'),
        ];

        $items = [
            ['title' => 'New Post 1', 'updated_at' => 1678886401],
        ];

        putenv('FIREBASE_SERVICE_ACCOUNT={"tokens":{"bot1":"dummy_token"}, "target_ids":{"bot1":"dummy_target"}}');

        $this->firestoreRepoMock->method('getRssFeeds')->willReturn($feeds);
        $this->firestoreRepoMock->method('getLastUpdatedAt')->willReturn(1678886400);
        $this->rssParserMock->method('parse')->willReturn($items);

        $this->lineNotifierMock->expects($this->once())->method('notify');
        $this->raindropNotifierMock->expects($this->never())->method('save');
        $this->firestoreRepoMock->expects($this->once())->method('saveLastUpdatedAt')->with('feed1', 1678886401);

        $this->feedProcessor->processAllFeeds();
        putenv('FIREBASE_SERVICE_ACCOUNT');
    }

    public function testProcessAllFeeds_RaindropSave()
    {
        $feeds = [
            new Feed('feed2', 'Test Feed 2', 'http://example.com/rss2', 'Save', null),
        ];

        $items = [
            ['title' => 'New Post 2', 'updated_at' => 1678886402],
        ];

        $this->firestoreRepoMock->method('getRssFeeds')->willReturn($feeds);
        $this->firestoreRepoMock->method('getLastUpdatedAt')->willReturn(1678886400);
        $this->firestoreRepoMock->method('getRaindropConfig')->willReturn(['access_token' => 'dummy_raindrop_token']);
        $this->rssParserMock->method('parse')->willReturn($items);

        $this->lineNotifierMock->expects($this->never())->method('notify');
        $this->raindropNotifierMock->expects($this->once())->method('save');
        $this->firestoreRepoMock->expects($this->once())->method('saveLastUpdatedAt')->with('feed2', 1678886402);

        $this->feedProcessor->processAllFeeds();
    }

    public function testProcessAllFeeds_NoNewItems()
    {
        $feeds = [
            new Feed('feed3', 'Test Feed 3', 'http://example.com/rss3', 'LINE', 'bot1'),
        ];

        $items = [
            ['title' => 'Old Post', 'updated_at' => 1678886400],
        ];

        $this->firestoreRepoMock->method('getRssFeeds')->willReturn($feeds);
        $this->firestoreRepoMock->method('getLastUpdatedAt')->willReturn(1678886400);
        $this->rssParserMock->method('parse')->willReturn($items);

        $this->lineNotifierMock->expects($this->never())->method('notify');
        $this->raindropNotifierMock->expects($this->never())->method('save');
        $this->firestoreRepoMock->expects($this->never())->method('saveLastUpdatedAt');

        $this->feedProcessor->processAllFeeds();
    }
}
