<?php

declare(strict_types=1);

namespace App;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\CollectionReference;
use Carbon\Carbon;

/**
 * Firestoreとのやり取りを行うリポジトリクラス
 */
class FirestoreRepository
{
    private const COLLECTION_FEEDS = 'feeds';
    private const COLLECTION_UPDATES = 'updates';

    private static ?FirestoreClient $client = null;
    private CollectionReference $collectionRoot;

    /**
     * コンストラクタ
     * @param FirestoreClient|null $firestore Firestoreクライアント
     */
    public function __construct(?FirestoreClient $firestore = null)
    {
        if ($firestore) {
            self::$client = $firestore;
        } elseif (self::$client === null) {
            $firebaseServiceAccountEnv = getenv('FIREBASE_SERVICE_ACCOUNT');
            $gcpServiceAccount = $firebaseServiceAccountEnv ? json_decode($firebaseServiceAccountEnv, true) : null;
            $options = [];
            if ($gcpServiceAccount) {
                $options['keyFile'] = $gcpServiceAccount;
                $options['projectId'] = $gcpServiceAccount['project_id'] ?? null;
            }

            if (getenv('FIRESTORE_EMULATOR_HOST')) {
                $options['projectId'] = $options['projectId'] ?? 'dummy-project';
            }

            // プロジェクトIDが設定されていない場合のフォールバック
            if (!isset($options['projectId']) || !$options['projectId']) {
                $options['projectId'] = 'rss-delivery-project';
            }

            self::$client = new FirestoreClient($options);
        }

        $this->collectionRoot = self::$client->collection(AppConfig::getFirestoreRootCollection());
    }

    /**
     * RSSフィードの設定を取得する
     *
     * @param string $sortBy 並び替えの基準となるフィールド名
     * @param string $direction 並び替えの方向 (asc または desc)
     * @return array<int, Feed> 設定情報の配列
     */
    public function getRssFeeds(string $sortBy = 'name', string $direction = 'asc'): array
    {
        $feeds = [];
        $query = $this->collectionRoot
            ->document(self::COLLECTION_FEEDS)
            ->collection(self::COLLECTION_FEEDS);

        // 有効なフィールド名のみを許可する
        $allowedFields = ['name', 'url', 'notify_method', 'notify_bot'];
        $orderField = in_array($sortBy, $allowedFields) ? $sortBy : 'name';
        $orderDirection = (strtolower($direction) === 'desc') ? 'descending' : 'ascending';

        $documents = $query->orderBy($orderField, $orderDirection)->documents();

        // 更新情報を取得
        $updates = [];
        $updateDocs = $this->collectionRoot
            ->document(self::COLLECTION_UPDATES)
            ->collection(self::COLLECTION_UPDATES)
            ->documents();
        foreach ($updateDocs as $doc) {
            if ($doc->exists()) {
                $updates[$doc->id()] = $doc->get('updated_at');
            }
        }

        foreach ($documents as $document) {
            if ($document->exists()) {
                $feedData = $document->data();
                $feedId = $document->id();
                $feeds[] = new Feed(
                    $feedId,
                    $feedData['name'],
                    $feedData['url'],
                    $feedData['notify_method'],
                    $feedData['notify_bot'] ?? null,
                    $feedData['enabled'] ?? true,
                    $updates[$feedId] ?? null
                );
            }
        }
        return $feeds;
    }

    /**
     * 指定されたIDのフィード設定を取得する
     *
     * @param string $id フィードID
     * @return Feed|null フィード情報。存在しない場合はnull
     */
    public function getFeed(string $id): ?Feed
    {
        $snapshot = $this->collectionRoot
            ->document(self::COLLECTION_FEEDS)
            ->collection(self::COLLECTION_FEEDS)
            ->document($id)
            ->snapshot();

        if ($snapshot->exists()) {
            $feedData = $snapshot->data();
            return new Feed(
                $snapshot->id(),
                $feedData['name'],
                $feedData['url'],
                $feedData['notify_method'],
                $feedData['notify_bot'] ?? null,
                $feedData['enabled'] ?? true
            );
        }

        return null;
    }

    /**
     * フィード設定を追加する
     *
     * @param array $data フィード設定データ
     * @return string 作成されたドキュメントのID
     */
    public function addFeed(array $data): string
    {
        $addedDocRef = $this->collectionRoot
            ->document(self::COLLECTION_FEEDS)
            ->collection(self::COLLECTION_FEEDS)
            ->add($data);

        return $addedDocRef->id();
    }

    /**
     * フィード設定を更新する
     *
     * @param string $id フィードID
     * @param array $data 更新するデータ
     * @return void
     */
    public function updateFeed(string $id, array $data): void
    {
        $this->collectionRoot
            ->document(self::COLLECTION_FEEDS)
            ->collection(self::COLLECTION_FEEDS)
            ->document($id)
            ->set($data, ['merge' => true]);
    }

    /**
     * フィード設定を削除する
     *
     * @param string $id フィードID
     * @return void
     */
    public function deleteFeed(string $id): void
    {
        $this->collectionRoot
            ->document(self::COLLECTION_FEEDS)
            ->collection(self::COLLECTION_FEEDS)
            ->document($id)
            ->delete();

        // 関連する更新情報も削除する
        $this->getUpdateDocument($id)->delete();
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
            $updatedAt = $document->get('updated_at');
            if (is_string($updatedAt)) {
                return Carbon::createFromFormat('Y/m/d H:i:s', $updatedAt, 'Asia/Tokyo')->getTimestamp();
            }
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
        $formattedDate = Carbon::createFromTimestamp($timestamp, 'Asia/Tokyo')->format('Y/m/d H:i:s');

        $this->getUpdateDocument($feedId)->set([
            'updated_at' => $formattedDate,
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
}
