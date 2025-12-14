<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\AppConfig;
use App\FirestoreRepository;
use App\LineNotifier;
use App\RssParser;
use App\RaindropNotifier;
use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Cloud Functionsのエントリポイントを登録
FunctionsFramework::cloudEvent('main_event', 'main_event');

/**
 * Pub/SubイベントをトリガーにRSSフィードを取得し、更新があれば通知する
 *
 * @param CloudEventInterface $event
 * @return void
 */
function main_event(CloudEventInterface $event): void
{
    $log = new Logger('main_http');
    $log->pushHandler(new StreamHandler('php://stderr'));
    $log->info('Function triggered with ' . AppConfig::getEnvironment() . ' environment.');

    // 依存関係をインスタンス化
    $firestoreRepo = new FirestoreRepository();
    $rssParser = new RssParser();
    $lineNotifier = new LineNotifier();
    $raindropNotifier = new RaindropNotifier();

    // 1. FirestoreからRSSフィード設定をすべて取得
    $feeds = $firestoreRepo->getRssFeeds();

    foreach ($feeds as $feed) {
        processSingleFeed($feed, $firestoreRepo, $rssParser, $lineNotifier, $raindropNotifier);
    }
}

/**
 * 個別のRSSフィードを処理する
 *
 * @param array<string, mixed> $feed
 * @param FirestoreRepository $firestoreRepo
 * @param RssParser $rssParser
 * @param LineNotifier $lineNotifier
 * @param RaindropNotifier $raindropNotifier
 * @return void
 */
function processSingleFeed(array $feed, FirestoreRepository $firestoreRepo, RssParser $rssParser, LineNotifier $lineNotifier, RaindropNotifier $raindropNotifier): void
{
    $feedId = $feed['id'];
    $feedUrl = $feed['url'];
    $feedName = $feed['name'];

    // 2. 最後に処理した記事の更新日時を取得
    $lastUpdatedAt = $firestoreRepo->getLastUpdatedAt($feedId) ?? 0;

    // 3. RSSパーサーでフィードを読み込む
    $items = $rssParser->parse($feedUrl);
    if (empty($items)) {
        return; // 記事がなければ終了
    }

    // 4. 新しい記事をフィルタリング
    $newItems = array_filter($items, function ($item) use ($lastUpdatedAt) {
        return isset($item['updated_at']) && $item['updated_at'] > $lastUpdatedAt;
    });

    if (empty($newItems)) {
        return; // 新しい記事がなければ終了
    }

    // 5. 新しい記事を更新日時の昇順でソート
    usort($newItems, function ($a, $b) {
        return $a['updated_at'] <=> $b['updated_at'];
    });

    // 6. 新しい記事を通知
    foreach ($newItems as $item) {
        if ($feed['notify_method'] === 'LINE') {
            notifyLine($feed, $item, $firestoreRepo, $lineNotifier);
        } elseif ($feed['notify_method'] === 'Save') {
            saveToRaindrop($item, $firestoreRepo, $raindropNotifier);
        }

        // 7. 処理した記事の更新日時を保存
        $firestoreRepo->saveLastUpdatedAt($feedId, $item['updated_at']);
    }
}

/**
 * Raindrop.ioへの保存を実行する
 *
 * @param array<string, mixed> $item
 * @param FirestoreRepository $firestoreRepo
 * @param RaindropNotifier $raindropNotifier
 * @return void
 */
function saveToRaindrop(array $item, FirestoreRepository $firestoreRepo, RaindropNotifier $raindropNotifier): void
{
    // Raindrop設定からアクセストークンを取得
    $config = $firestoreRepo->getRaindropConfig();
    $accessToken = $config['access_token'] ?? null;

    if (!$accessToken) {
        error_log("Access token for Raindrop.io not found.");
        return;
    }

    $raindropNotifier->save($accessToken, $item);
}

/**
 * LINE通知を実行する
 *
 * @param array<string, mixed> $feed
 * @param array<string, mixed> $item
 * @param FirestoreRepository $firestoreRepo
 * @param LineNotifier $lineNotifier
 * @return void
 */
function notifyLine(array $feed, array $item, FirestoreRepository $firestoreRepo, LineNotifier $lineNotifier): void
{
    $botId = $feed['notify_bot'] ?? null;

    if (!$botId) {
        error_log("LINE notification for feed '{$feed['name']}' is missing bot ID.");
        return;
    }

    // BOT設定からアクセストークンを取得
    $lineConfig = json_decode(getenv('FIREBASE_CONFIG'), true);

    $lineNotifier->notify(
        $lineConfig->tokens->$botId,
        $lineConfig->target_ids->$botId,
        $feed['name'],
        $item
    );
}
