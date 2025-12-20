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
    private $feedProcessor;

    protected function setUp(): void
    {
        // 依存関係のモックを作成
        $this->firestoreRepoMock = $this->createMock(FirestoreRepository::class);
        $this->rssParserMock = $this->createMock(RssParser::class);
        $this->lineNotifierMock = $this->createMock(LineNotifier::class);
        $this->raindropNotifierMock = $this->createMock(RaindropNotifier::class);
        $this->loggerMock = $this->createMock(Logger::class);

        // テスト対象のクラスをインスタンス化
        $this->feedProcessor = new FeedProcessor(
            $this->firestoreRepoMock,
            $this->rssParserMock,
            $this->lineNotifierMock,
            $this->raindropNotifierMock,
            $this->loggerMock
        );
    }

    /**
     * @test
     * @testdox test_初回実行時は通知がスキップされること
     */
    public function test_初回実行時は通知がスキップされること(): void
    {
        $feed = new Feed('test_id', 'Test Feed', 'http://example.com/rss', 'LINE', 'test_bot');

        $this->firestoreRepoMock->expects($this->once())
            ->method('getRssFeeds')
            ->willReturn([$feed]);

        // Firestoreからはnullが返る（初回実行）
        $this->firestoreRepoMock->expects($this->once())
            ->method('getLastUpdatedAt')
            ->with('test_id')
            ->willReturn(null);

        // RSSパーサーは2件のアイテムを返す
        $items = [
            ['title' => 'Item 1', 'updated_at' => 1672531201], // 2023-01-01 09:00:01
            ['title' => 'Item 2', 'updated_at' => 1672531202], // 2023-01-01 09:00:02
        ];
        $this->rssParserMock->expects($this->once())
            ->method('parse')
            ->with('http://example.com/rss')
            ->willReturn($items);

        // 通知メソッドは呼ばれないはず
        $this->lineNotifierMock->expects($this->never())->method('notify');
        $this->raindropNotifierMock->expects($this->never())->method('save');

        // 最終更新日時は最新のアイテムで保存されるはず
        $this->firestoreRepoMock->expects($this->once())
            ->method('saveLastUpdatedAt')
            ->with('test_id', 1672531202);

        $this->feedProcessor->processAllFeeds();
    }

    /**
     * @test
     * @testdox test_新規アイテムが21件の場合は通知がスキップされること
     */
    public function test_新規アイテムが21件の場合は通知がスキップされること(): void
    {
        $feed = new Feed('test_id', 'Test Feed', 'http://example.com/rss', 'LINE', 'test_bot');

        $this->firestoreRepoMock->expects($this->once())
            ->method('getRssFeeds')
            ->willReturn([$feed]);

        // 既存の更新日時を返す
        $this->firestoreRepoMock->expects($this->once())
            ->method('getLastUpdatedAt')
            ->with('test_id')
            ->willReturn(1672531200); // 2023-01-01 09:00:00

        // 21件の新しいアイテムを生成
        $items = [];
        for ($i = 1; $i <= 21; $i++) {
            $items[] = ['title' => "Item {$i}", 'updated_at' => 1672531200 + $i];
        }
        $this->rssParserMock->expects($this->once())
            ->method('parse')
            ->with('http://example.com/rss')
            ->willReturn($items);

        // 通知メソッドは呼ばれないはず
        $this->lineNotifierMock->expects($this->never())->method('notify');
        $this->raindropNotifierMock->expects($this->never())->method('save');

        // 最終更新日時は最新のアイテムで保存されるはず
        $this->firestoreRepoMock->expects($this->once())
            ->method('saveLastUpdatedAt')
            ->with('test_id', 1672531221); // 最後のアイテムのタイムスタンプ

        $this->feedProcessor->processAllFeeds();
    }

    /**
     * @test
     * @testdox test_通常の通知処理が正しく行われること
     */
    public function test_通常の通知処理が正しく行われること(): void
    {
        $feed = new Feed('test_id', 'Test Feed', 'http://example.com/rss', 'LINE', 'test_bot');

        $this->firestoreRepoMock->expects($this->once())
            ->method('getRssFeeds')
            ->willReturn([$feed]);

        $this->firestoreRepoMock->expects($this->once())
            ->method('getLastUpdatedAt')
            ->with('test_id')
            ->willReturn(1672531200); // 2023-01-01 09:00:00

        $items = [
            ['title' => 'Old Item', 'updated_at' => 1672531200],
            ['title' => 'New Item 1', 'updated_at' => 1672531201],
            ['title' => 'New Item 2', 'updated_at' => 1672531202],
        ];
        $this->rssParserMock->expects($this->once())
            ->method('parse')
            ->with('http://example.com/rss')
            ->willReturn($items);

        // 新規アイテム2件分、通知が呼ばれるはず
        $this->lineNotifierMock->expects($this->exactly(2))->method('notify');
        $this->raindropNotifierMock->expects($this->never())->method('save');

        // 最終更新日時は最新のアイテムで保存されるはず
        $this->firestoreRepoMock->expects($this->once())
            ->method('saveLastUpdatedAt')
            ->with('test_id', 1672531202);

        $this->feedProcessor->processAllFeeds();
    }
}
