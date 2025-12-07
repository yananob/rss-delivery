<?php

namespace Tests;

use App\FirestoreRepository;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use PHPUnit\Framework\TestCase;

class FirestoreRepositoryTest extends TestCase
{
    /**
     * @test
     */
    public function test_RSSフィード設定を正しく取得できる(): void
    {
        // DocumentSnapshotのモックを作成
        $snapshot1 = $this->createMock(DocumentSnapshot::class);
        $snapshot1->method('exists')->willReturn(true);
        $snapshot1->method('id')->willReturn('feed1');
        $snapshot1->method('data')->willReturn(['name' => 'Feed 1', 'url' => 'https://example.com/feed1']);

        $snapshot2 = $this->createMock(DocumentSnapshot::class);
        $snapshot2->method('exists')->willReturn(true);
        $snapshot2->method('id')->willReturn('feed2');
        $snapshot2->method('data')->willReturn(['name' => 'Feed 2', 'url' => 'https://example.com/feed2']);

        // DocumentReferenceのモックを作成 (documents()がスナップショットを返すように)
        $collectionMock = $this->createMock(CollectionReference::class);
        $collectionMock->method('documents')->willReturn([$snapshot1, $snapshot2]);

        // DocumentReference (root) のモック
        $docRefMock = $this->createMock(DocumentReference::class);
        $docRefMock->method('collection')->willReturn($collectionMock);

        // CollectionReference (root) のモック
        $rootColMock = $this->createMock(CollectionReference::class);
        $rootColMock->method('document')->willReturn($docRefMock);

        // FirestoreClientのモックを作成
        $firestoreClientMock = $this->createMock(FirestoreClient::class);
        $firestoreClientMock->method('collection')->willReturn($rootColMock);

        // テスト対象のクラスをインスタンス化
        $repository = new FirestoreRepository($firestoreClientMock);
        $feeds = $repository->getRssFeeds();

        $this->assertCount(2, $feeds);
        $this->assertEquals('feed1', $feeds[0]['id']);
        $this->assertEquals('Feed 1', $feeds[0]['name']);
        $this->assertEquals('feed2', $feeds[1]['id']);
        $this->assertEquals('Feed 2', $feeds[1]['name']);
    }
}
