<?php

namespace Tests;

use App\FirestoreRepository;
use Google\Cloud\Firestore\FirestoreClient;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
class FirestoreRepositoryTest extends TestCase
{
    private FirestoreRepository $repository;
    private FirestoreClient $firestore;
    private static string $testFeedId1;
    private static string $testFeedId2;
    private static string $projectId = 'test-project';

    public static function setUpBeforeClass(): void
    {
        self::$testFeedId1 = 'test-feed-id-1-' . uniqid();
        self::$testFeedId2 = 'test-feed-id-2-' . uniqid();
    }

    protected function setUp(): void
    {
        // Firestoreエミュレータを使用するように環境変数を設定
        putenv('FIRESTORE_EMULATOR_HOST=localhost:8080');

        // 実際のFirestoreクライアントを使用
        $this->firestore = new FirestoreClient(['projectId' => self::$projectId]);
        $this->repository = new FirestoreRepository($this->firestore);

        // テストデータを投入
        $this->firestore->collection('rss-delivery/rss_feeds/rss_feeds')->document(self::$testFeedId1)->set([
            'name' => 'Integration Test Feed 1',
            'url' => 'https://example.com/integration1.xml',
            'notify_method' => 'LINE',
            'notify_bot' => 'bot_A',
            'notify_target' => 'target_X',
        ]);
        $this->firestore->collection('rss-delivery/rss_feeds/rss_feeds')->document(self::$testFeedId2)->set([
            'name' => 'Integration Test Feed 2',
            'url' => 'https://example.com/integration2.xml',
            'notify_method' => 'Save',
        ]);
        $this->firestore->collection('rss-delivery/line_bots/line_bots')->document('bot_A')->set([
            'access_token' => 'dummy_token_for_bot_a',
        ]);
    }

    protected function tearDown(): void
    {
        // テストデータをクリーンアップ
        $this->firestore->collection('rss-delivery/rss_feeds/rss_feeds')->document(self::$testFeedId1)->delete();
        $this->firestore->collection('rss-delivery/rss_feeds/rss_feeds')->document(self::$testFeedId2)->delete();
        $this->firestore->collection('rss-delivery/line_bots/line_bots')->document('bot_A')->delete();
        $this->firestore->collection('rss-delivery/updates/updates')->document(self::$testFeedId1)->delete();

        // 環境変数を元に戻す
        putenv('FIRESTORE_EMULATOR_HOST');
    }

    /**
     * @test
     */
    public function test_RSSフィード設定を正しく取得できる(): void
    {
        $feeds = $this->repository->getRssFeeds();

        $testFeed1 = null;
        foreach($feeds as $feed) {
            if ($feed['id'] === self::$testFeedId1) {
                $testFeed1 = $feed;
                break;
            }
        }

        $this->assertNotNull($testFeed1, "Test feed 1 should be found.");
        $this->assertEquals('Integration Test Feed 1', $testFeed1['name']);
        $this->assertEquals('LINE', $testFeed1['notify_method']);
    }

    /**
     * @test
     */
    public function test_最終更新日時の保存と取得ができる(): void
    {
        $timestamp = time();
        $this->repository->saveLastUpdatedAt(self::$testFeedId1, $timestamp);

        $lastUpdatedAt = $this->repository->getLastUpdatedAt(self::$testFeedId1);

        $this->assertEquals($timestamp, $lastUpdatedAt);
    }

    /**
     * @test
     */
    public function test_LINEボットの設定を取得できる(): void
    {
        $config = $this->repository->getLineBotConfig('bot_A');
        $this->assertNotNull($config);
        $this->assertEquals('dummy_token_for_bot_a', $config['access_token']);
    }
}
