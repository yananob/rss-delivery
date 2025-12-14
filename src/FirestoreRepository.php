<?php

namespace App;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\DocumentReference;

/**
 * Firestoreとのやり取りを行うリポジトリクラス
 */
class FirestoreRepository
{
    private const COLLECTION_ROOT = 'rss-delivery';
    private const COLLECTION_FEEDS = 'rss_feeds';
    private const COLLECTION_UPDATES = 'updates';
    private const COLLECTION_LINE_BOTS = 'line_bots';
    private const COLLECTION_RAINDROP = 'raindrop_configs';

    private static ?FirestoreClient $client = null;

    /**
     * コンストラクタ
     * @param FirestoreClient|null $firestore Firestoreクライアント
     */
    public function __construct(FirestoreClient $firestore = null)
    {
        if (self::$client === null) {
            $gcpServiceAccount = json_decode(getenv('FIREBASE_CONFIG'), true);
            self::$client = new FirestoreClient(
                [
                    'keyFile' => $gcpServiceAccount,
                ]
            );
            return self::$client;
        }
    }

    /**
     * RSSフィードの設定を取得する
     *
     * @return array<int, array<string, mixed>> 設定情報の配列
     */
    public function getRssFeeds(): array
    {
        $feeds = [];
        $documents = $this->client
            ->collection(self::COLLECTION_ROOT)
            ->document(self::COLLECTION_FEEDS)
            ->collection(self::COLLECTION_FEEDS)
            ->documents();

        foreach ($documents as $document) {
            if ($document->exists()) {
                $feedData = $document->data();
                $feedData['id'] = $document->id(); // ドキュメントIDをIDとして追加
                $feeds[] = $feedData;
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
        return $this->client
            ->collection(self::COLLECTION_ROOT)
            ->document(self::COLLECTION_UPDATES)
            ->collection(self::COLLECTION_UPDATES)
            ->document($feedId);
    }

    /**
     * LINE BOTの設定を取得する
     *
     * @param string $botId BOTの識別子
     * @return array<string, mixed>|null BOT設定情報。存在しない場合はnull
     */
    public function getLineBotConfig(string $botId): ?array
    {
        $document = $this->client
            ->collection(self::COLLECTION_ROOT)
            ->document(self::COLLECTION_LINE_BOTS)
            ->collection(self::COLLECTION_LINE_BOTS)
            ->document($botId)
            ->snapshot();

        if ($document->exists()) {
            return $document->data();
        }

        return null;
    }

    /**
     * Raindrop.ioの設定を取得する
     *
     * @return array<string, mixed>|null 設定情報。存在しない場合はnull
     */
    public function getRaindropConfig(): ?array
    {
        // "Save" の設定は一つと仮定し、固定のドキュメントID 'main' を使用する
        $document = $this->client
            ->collection(self::COLLECTION_ROOT)
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
