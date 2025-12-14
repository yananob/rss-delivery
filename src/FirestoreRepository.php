<?php

declare(strict_types=1);

namespace App;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\CollectionReference;

/**
 * Firestoreとのやり取りを行うリポジトリクラス
 */
class FirestoreRepository
{
    private const COLLECTION_FEEDS = 'feeds';
    private const COLLECTION_UPDATES = 'updates';
    private const COLLECTION_RAINDROP = 'raindrop_configs';

    private static ?FirestoreClient $client = null;
    private CollectionReference $collectionRoot;

    /**
     * コンストラクタ
     * @param FirestoreClient|null $firestore Firestoreクライアント
     */
    public function __construct(FirestoreClient $firestore = null)
    {
        if ($firestore) {
            self::$client = $firestore;
        } elseif (self::$client === null) {
            $gcpServiceAccount = json_decode(getenv('FIREBASE_CONFIG'), true);
            self::$client = new FirestoreClient(
                [
                    'keyFile' => $gcpServiceAccount,
                ]
            );
        }

        $this->collectionRoot = self::$client->collection(AppConfig::getFirestoreRootCollection());
    }

    /**
     * RSSフィードの設定を取得する
     *
     * @return array<int, Feed> 設定情報の配列
     */
    public function getRssFeeds(): array
    {
        $feeds = [];
        $documents = $this->collectionRoot
            ->document(self::COLLECTION_FEEDS)
            ->collection(self::COLLECTION_FEEDS)
            ->documents();

        foreach ($documents as $document) {
            if ($document->exists()) {
                $feedData = $document->data();
                $feeds[] = new Feed(
                    $document->id(),
                    $feedData['name'],
                    $feedData['url'],
                    $feedData['notify_method'],
                    $feedData['notify_bot'] ?? null
                );
            }
        }
        return $feeds;
    }

    /**
     * 指定されたフィードIDの最終更新日時を取得する
     *
     * @param string $feedId フィード設定のドキュメントID
     * @return int|null 最終更新日時のタイムスタンプ。存在しない場合はnull
     */
    public function getLastUpdatedAt(string $feedId): ?int
    {
        $document = $this->getUpdateDocument($feedId)->snapshot();

        if ($document->exists()) {
            return $document->get('updated_at');
        }

        return null;
    }

    /**
     * 最終更新日時を保存する
     *
     * @param string $feedId フィード設定のドキュメントID
     * @param int $timestamp 保存するタイムスタンプ
     * @return void
     */
    public function saveLastUpdatedAt(string $feedId, int $timestamp): void
    {
        $this->getUpdateDocument($feedId)->set([
            'updated_at' => $timestamp,
        ], ['merge' => true]);
    }

    /**
     * 更新情報ドキュメントへの参照を取得する
     *
     * @param string $feedId フィード設定のドキュメントID
     * @return DocumentReference
     */
    private function getUpdateDocument(string $feedId): DocumentReference
    {
        return $this->collectionRoot
            ->document(self::COLLECTION_UPDATES)
            ->collection(self::COLLECTION_UPDATES)
            ->document($feedId);
    }

    /**
     * Raindrop.ioの設定を取得する
     *
     * @return array<string, mixed>|null 設定情報。存在しない場合はnull
     */
    public function getRaindropConfig(): ?array
    {
        // "Save" の設定は一つと仮定し、固定のドキュメントID 'main' を使用する
        $document = $this->collectionRoot
            ->document(self::COLLECTION_RAINDROP)
            ->collection(self::COLLECTION_RAINDROP)
            ->document('main')
            ->snapshot();

        if ($document->exists()) {
            return $document->data();
        }

        return null;
    }
}
