<?php

declare(strict_types=1);

namespace Tests;

use App\AppConfig;
use App\FirestoreRepository;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\FirestoreClient;
use PHPUnit\Framework\TestCase;

class FirestoreRepositoryTest extends TestCase
{
    private FirestoreRepository $repository;
    private FirestoreClient $firestore;
    private CollectionReference $collectionRoot;
    private string $testFeedId1;
    private string $testFeedId2;
    private const COLLECTION_FEEDS = 'feeds';
    // private static string $projectId = 'test-project';

    // public static function setUpBeforeClass(): void {
    // }

    protected function setUp(): void
    {
        parent::setUp();

        // Firestoreエミュレータを使用するように環境変数を設定
        // putenv('FIRESTORE_EMULATOR_HOST=localhost:8080');

        // 実際のFirestoreクライアントを使用
        $gcpServiceAccount = json_decode(getenv('FIREBASE_SERVICE_ACCOUNT'), true);
        $this->firestore = new FirestoreClient(
            [
                'keyFile' => $gcpServiceAccount,
            ]
        );
        $this->collectionRoot = $this->firestore->collection(AppConfig::getFirestoreRootCollection());
        $this->repository = new FirestoreRepository($this->firestore);

        $this->testFeedId1 = 'test-feed-id-1-' . uniqid();
        $this->testFeedId2 = 'test-feed-id-2-' . uniqid();
        // テストデータを投入
        $this->collectionRoot->document(self::COLLECTION_FEEDS)->collection(self::COLLECTION_FEEDS)->document($this->testFeedId1)->set([
            'name' => 'Integration Test Feed 1',
            'url' => 'https://example.com/integration1.xml',
            'notify_method' => 'LINE',
            'notify_bot' => 'bot_A',
            'notify_target' => 'target_X',
        ]);
        $this->collectionRoot->document(self::COLLECTION_FEEDS)->collection(self::COLLECTION_FEEDS)->document($this->testFeedId2)->set([
            'name' => 'Integration Test Feed 2',
            'url' => 'https://example.com/integration2.xml',
            'notify_method' => 'Save',
        ]);
        $this->collectionRoot->document('line_bots')->collection('line_bots')->document('bot_A')->set([
            'access_token' => 'dummy_token_for_bot_a',
        ]);
    }

    protected function tearDown(): void
    {
        // テストデータをクリーンアップ
        $this->collectionRoot->document(self::COLLECTION_FEEDS)->collection(self::COLLECTION_FEEDS)->document($this->testFeedId1)->delete();
        $this->collectionRoot->document(self::COLLECTION_FEEDS)->collection(self::COLLECTION_FEEDS)->document($this->testFeedId2)->delete();
        $this->collectionRoot->document('line_bots')->collection('line_bots')->document('bot_A')->delete();
        $this->collectionRoot->document('updates')->collection('updates')->document($this->testFeedId1)->delete();

        // // 環境変数を元に戻す
        // putenv('FIRESTORE_EMULATOR_HOST');

        parent::tearDown();
    }

    /**
     * @test
     */
    public function test_RSSフィード設定を正しく取得できる(): void
    {
        $feeds = $this->repository->getRssFeeds();

        $testFeed1 = null;
        foreach($feeds as $feed) {
            if ($feed->getId() === $this->testFeedId1) {
                $testFeed1 = $feed;
                break;
            }
        }

        $this->assertNotNull($testFeed1, "Test feed 1 should be found.");
        $this->assertEquals('Integration Test Feed 1', $testFeed1->getName());
        $this->assertEquals('LINE', $testFeed1->getNotifyMethod());
    }

    /**
     * @test
     */
    public function test_最終更新日時の保存と取得ができる(): void
    {
        $timestamp = time();
        $this->repository->saveLastUpdatedAt($this->testFeedId1, $timestamp);

        $lastUpdatedAt = $this->repository->getLastUpdatedAt($this->testFeedId1);

        $this->assertEquals($timestamp, $lastUpdatedAt);
    }
}
