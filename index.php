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
    $log = new Logger('main_event_logger');
    $log->pushHandler(new StreamHandler('php://stderr'));
    $log->info('Function main_event triggered with ' . AppConfig::getEnvironment() . ' environment.');

    // 依存関係をインスタンス化
    $firestoreRepo = new FirestoreRepository();
    $rssParser = new RssParser();
    $lineNotifier = new LineNotifier();
    $raindropNotifier = new RaindropNotifier();

    $log->debug('Fetching RSS feed configurations from Firestore.');
    // 1. FirestoreからRSSフィード設定をすべて取得
    $feeds = $firestoreRepo->getRssFeeds();
    $log->info(count($feeds) . ' RSS feed configurations fetched.');

    if (empty($feeds)) {
        $log->warning('No RSS feed configurations found in Firestore. Exiting function.');
        return;
    }

    foreach ($feeds as $feed) {
        $log->debug('Processing single feed: ' . $feed['name'] . ' (ID: ' . $feed['id'] . ')');
        processSingleFeed($feed, $firestoreRepo, $rssParser, $lineNotifier, $raindropNotifier, $log);
        $log->debug('Finished processing feed: ' . $feed['name']);
    }

    $log->info('Function main_event completed successfully.');
}

/**
 * 個別のRSSフィードを処理する
 *
 * @param array<string, mixed> $feed
 * @param FirestoreRepository $firestoreRepo
 * @param RssParser $rssParser
 * @param LineNotifier $lineNotifier
 * @param RaindropNotifier $raindropNotifier
 * @param Logger $log
 * @return void
 */
function processSingleFeed(array $feed, FirestoreRepository $firestoreRepo, RssParser $rssParser, LineNotifier $lineNotifier, RaindropNotifier $raindropNotifier, Logger $log): void
{
    $feedId = $feed['id'];
    $feedUrl = $feed['url'];
    $feedName = $feed['name'];
    $log->info("Processing feed: [{$feedName}] (ID: {$feedId}, URL: {$feedUrl})");

    // 2. 最後に処理した記事の更新日時を取得
    $lastUpdatedAt = $firestoreRepo->getLastUpdatedAt($feedId) ?? 0;
    $log->debug("Last updated timestamp for [{$feedName}]: {$lastUpdatedAt}");

    // 3. RSSパーサーでフィードを読み込む
    $log->debug("Parsing RSS feed from URL: {$feedUrl}");
    $items = $rssParser->parse($feedUrl);
    $log->debug('Parsed ' . count($items) . ' items from feed: ' . $feedName);

    if (empty($items)) {
        $log->info("No items found in feed [{$feedName}]. Skipping.");
        return; // 記事がなければ終了
    }

    // 4. 新しい記事をフィルタリング
    $log->debug("Filtering new items for feed [{$feedName}].");
    $newItems = array_filter($items, function ($item) use ($lastUpdatedAt) {
        return isset($item['updated_at']) && $item['updated_at'] > $lastUpdatedAt;
    });

    if (empty($newItems)) {
        $log->info("No new items found for feed [{$feedName}]. Skipping.");
        return; // 新しい記事がなければ終了
    }

    $log->info(count($newItems) . " new items found for feed [{$feedName}].");

    // 5. 新しい記事を更新日時の昇順でソート
    usort($newItems, function ($a, $b) {
        return $a['updated_at'] <=> $b['updated_at'];
    });

    // 6. 新しい記事を通知
    foreach ($newItems as $item) {
        $log->info("Processing new item '{$item['title']}' (Updated: {$item['updated_at']}) for feed [{$feedName}].");
        if ($feed['notify_method'] === 'LINE') {
            notifyLine($feed, $item, $firestoreRepo, $lineNotifier, $log);
        } elseif ($feed['notify_method'] === 'Save') {
            saveToRaindrop($item, $firestoreRepo, $raindropNotifier, $log);
        } else {
            $log->warning("Unknown notification method '{$feed['notify_method']}' for feed [{$feedName}]. Item '{$item['title']}' not notified.");
        }

        // 7. 処理した記事の更新日時を保存
        $firestoreRepo->saveLastUpdatedAt($feedId, $item['updated_at']);
        $log->debug("Saved last updated timestamp ({$item['updated_at']}) for feed [{$feedName}].");
    }
        $log->info("Finished processing all new items for feed [{$feedName}].");
    }
    
    /**
     * Raindrop.ioへの保存を実行する
     *
     * @param array<string, mixed> $item
     * @param FirestoreRepository $firestoreRepo
     * @param RaindropNotifier $raindropNotifier
     * @param Logger $log
     * @return void
     */
    function saveToRaindrop(array $item, FirestoreRepository $firestoreRepo, RaindropNotifier $raindropNotifier, Logger $log): void
    {
        $log->info("Attempting to save item '{$item['title']}' to Raindrop.io.");
        // Raindrop設定からアクセストークンを取得
        $config = $firestoreRepo->getRaindropConfig();
        $accessToken = $config['access_token'] ?? null;
    
        if (!$accessToken) {
            $log->error("Access token for Raindrop.io not found. Item '{$item['title']}' not saved.");
            return;
        }
    
        $raindropNotifier->save($accessToken, $item);
        $log->info("Successfully saved item '{$item['title']}' to Raindrop.io.");
    }
    
    /**
     * LINE通知を実行する
     *
     * @param array<string, mixed> $feed
     * @param array<string, mixed> $item
     * @param FirestoreRepository $firestoreRepo
     * @param LineNotifier $lineNotifier
     * @param Logger $log
     * @return void
     */
    function notifyLine(array $feed, array $item, FirestoreRepository $firestoreRepo, LineNotifier $lineNotifier, Logger $log): void
    {
        $log->info("Attempting to send LINE notification for item '{$item['title']}' for feed '{$feed['name']}'.");
        $botId = $feed['notify_bot'] ?? null;
    
        if (!$botId) {
            $log->error("LINE notification for feed '{$feed['name']}' is missing bot ID. Item '{$item['title']}' not notified.");
            return;
        }
    
        // BOT設定からアクセストークンを取得
        $lineConfig = json_decode(getenv('FIREBASE_CONFIG'), true);
    
        // TODO: Consider error handling for json_decode and missing tokens/target_ids
        if (!isset($lineConfig['tokens'][$botId]) || !isset($lineConfig['target_ids'][$botId])) {
            $log->error("LINE configuration (token or target ID) for bot '{$botId}' is missing. Item '{$item['title']}' not notified.");
            return;
        }
    
        $lineNotifier->notify(
            $lineConfig['tokens'][$botId],
            $lineConfig['target_ids'][$botId],
            $feed['name'],
            $item
        );
        $log->info("Successfully sent LINE notification for item '{$item['title']}'.");
    }
