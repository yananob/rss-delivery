<?php

declare(strict_types=1);

namespace Tests;

use App\AppConfig;
use App\FirestoreRepository;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\FirestoreClient;
use PHPUnit\Framework\TestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class FirestoreRepositoryTest extends TestCase
{
    private FirestoreRepository $repository;
    private FirestoreClient $firestore;
    private CollectionReference $collectionRoot;
    private string $testFeedId1;
    private string $testFeedId2;
    private const COLLECTION_FEEDS = 'feeds';
    private const COLLECTION_UPDATES = 'updates';

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
        $this->collectionRoot->document(self::COLLECTION_UPDATES)->collection(self::COLLECTION_UPDATES)->document($this->testFeedId1)->delete();

        // // 環境変数を元に戻す
        // putenv('FIRESTORE_EMULATOR_HOST');

        parent::tearDown();
    }

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

    public function test_最終更新日時をCarbonを使い文字列で保存しタイムスタンプで取得できる(): void
    {
        $timestamp = Carbon::now('Asia/Tokyo')->getTimestamp();
        $this->repository->saveLastUpdatedAt($this->testFeedId1, $timestamp);

        // Firestoreに文字列で保存されているか直接確認
        $document = $this->collectionRoot
            ->document(self::COLLECTION_UPDATES)
            ->collection(self::COLLECTION_UPDATES)
            ->document($this->testFeedId1)
            ->snapshot();

        $this->assertTrue($document->exists(), 'Update document should exist.');
        $savedValue = $document->get('updated_at');
        $this->assertIsString($savedValue, 'The saved value should be a string.');

        $expectedFormat = Carbon::createFromTimestamp($timestamp, 'Asia/Tokyo')->format('Y/m/d H:i:s');
        $this->assertEquals($expectedFormat, $savedValue, 'The saved string format is incorrect.');

        // getLastUpdatedAtが正しくタイムスタンプを返すか確認
        $lastUpdatedAt = $this->repository->getLastUpdatedAt($this->testFeedId1);
        $this->assertEquals($timestamp, $lastUpdatedAt, 'The retrieved timestamp should match the original.');
    }

    public function test_最終更新日時が存在しない場合にnullが返ること(): void
    {
        $lastUpdatedAt = $this->repository->getLastUpdatedAt('non-existent-feed-id');
        $this->assertNull($lastUpdatedAt, 'Should return null for non-existent feed ID.');
    }
}
