<?php

declare(strict_types=1);

namespace App;

use Monolog\Logger;
use DateTime;
use DateTimeZone;

/**
 * RSSフィードの取得、更新チェック、および通知処理を管理するクラス
 */
class FeedProcessor
{
    private const MAX_NEW_ITEMS_NOTIFY_LIMIT = 20;

    public function __construct(
        private FirestoreRepository $firestoreRepo,
        private RssParser $rssParser,
        private LineNotifier $lineNotifier,
        private RaindropNotifier $raindropNotifier,
        private Logger $log
    ) {
    }

    /**
     * 設定されているすべてのRSSフィードを処理する
     */
    public function processAllFeeds(): void
    {
        $this->log->debug('Fetching RSS feed configurations from Firestore.');
        $feeds = $this->firestoreRepo->getRssFeeds();
        $this->log->info(count($feeds) . ' RSS feed configurations fetched.');

        if (empty($feeds)) {
            $this->log->warning('No RSS feed configurations found in Firestore. Exiting function.');
            return;
        }

        foreach ($feeds as $feed) {
            if (!$feed->isEnabled()) {
                $this->log->info('Skipping disabled feed: ' . $feed->getName() . ' (ID: ' . $feed->getId() . ')');
                continue;
            }
            $this->log->debug('Processing single feed: ' . $feed->getName() . ' (ID: ' . $feed->getId() . ')');
            $this->processSingleFeed($feed);
            $this->log->debug('Finished processing feed: ' . $feed->getName());
        }
    }

    /**
     * 単一のRSSフィードを処理する
     *
     * @param Feed $feed
     */
    private function processSingleFeed(Feed $feed): void
    {
        $feedId = $feed->getId();
        $feedUrl = $feed->getUrl();
        $feedName = $feed->getName();
        $this->log->info("Processing feed: [{$feedName}] (ID: {$feedId}, URL: {$feedUrl})");

        $rawLastUpdatedAt = $this->firestoreRepo->getLastUpdatedAt($feedId);
        $isFirstRun = ($rawLastUpdatedAt === null);
        $lastUpdatedAt = $rawLastUpdatedAt ?? 0;
        $this->log->debug("Last updated timestamp for [{$feedName}]: {$lastUpdatedAt}");

        $this->log->debug("Parsing RSS feed from URL: {$feedUrl}");
        $items = $this->rssParser->parse($feedUrl);
        $this->log->debug('Parsed ' . count($items) . ' items from feed: ' . $feedName);

        if (empty($items)) {
            $this->log->info("No items found in feed [{$feedName}]. Skipping.");
            return;
        }

        $newItems = $this->filterNewItems($items, $lastUpdatedAt);
        if (empty($newItems)) {
            $this->log->info("No new items found for feed [{$feedName}]. Skipping.");
            return;
        }

        $newItemsCount = count($newItems);
        $this->log->info("{$newItemsCount} new items found for feed [{$feedName}].");

        // 日時昇順にソート
        usort($newItems, fn(array $a, array $b): int => ($a['updated_at'] ?? 0) <=> ($b['updated_at'] ?? 0));

        // 最新の記事の日時を保存
        $latestItemTimestamp = end($newItems)['updated_at'];
        $this->firestoreRepo->saveLastUpdatedAt($feedId, $latestItemTimestamp);
        $this->log->debug("Saved last updated timestamp ({$latestItemTimestamp}) for feed [{$feedName}].");

        if ($this->shouldSkipNotification($feedName, $isFirstRun, $newItemsCount)) {
            return;
        }

        foreach ($newItems as $item) {
            $this->log->info("Processing new item '{$item['title']}' (Updated: {$item['updated_at']}) for feed [{$feedName}].");
            $this->dispatchNotification($feed, $item);
        }
        $this->log->info("Finished processing all new items for feed [{$feedName}].");
    }

    /**
     * 最終更新日時より新しい記事を抽出する
     *
     * @param array<int, array<string, mixed>> $items
     * @param int $lastUpdatedAt
     * @return array<int, array<string, mixed>>
     */
    private function filterNewItems(array $items, int $lastUpdatedAt): array
    {
        return array_values(array_filter($items, function (array $item) use ($lastUpdatedAt): bool {
            return isset($item['updated_at']) && is_int($item['updated_at']) && $item['updated_at'] > $lastUpdatedAt;
        }));
    }

    /**
     * 通知処理をスキップすべきかどうか判定する
     *
     * @param string $feedName
     * @param bool $isFirstRun
     * @param int $newItemsCount
     * @return bool
     */
    private function shouldSkipNotification(string $feedName, bool $isFirstRun, int $newItemsCount): bool
    {
        if ($isFirstRun) {
            $this->log->info("First run for feed '{$feedName}'. Skipping notification process.");
            return true;
        }

        if ($newItemsCount > self::MAX_NEW_ITEMS_NOTIFY_LIMIT) {
            $this->log->info("Too many new items ({$newItemsCount}) for feed '{$feedName}'. Skipping notification process to avoid flooding.");
            return true;
        }

        return false;
    }

    /**
     * 通知方法に応じて通知を振り分ける
     *
     * @param Feed $feed
     * @param array<string, mixed> $item
     */
    private function dispatchNotification(Feed $feed, array $item): void
    {
        if ($feed->getNotifyMethod() === 'LINE') {
            $this->notifyLine($feed, $item);
        } elseif ($feed->getNotifyMethod() === 'Save') {
            $this->saveToRaindrop($item);
        } else {
            $this->log->warning("Unknown notification method '{$feed->getNotifyMethod()}' for feed [{$feed->getName()}]. Item '{$item['title']}' not notified.");
        }
    }

    /**
     * Raindrop.ioへ保存する
     *
     * @param array<string, mixed> $item
     */
    private function saveToRaindrop(array $item): void
    {
        $this->log->info("Attempting to save item '{$item['title']}' to Raindrop.io.");
        $accessToken = getenv("RAINDROP_KEY");

        if (!$accessToken) {
            $this->log->error("Access token for Raindrop.io not found. Item '{$item['title']}' not saved.");
            return;
        }

        if ($this->raindropNotifier->save($accessToken, $item)) {
            $this->log->info("Successfully saved item '{$item['title']}' to Raindrop.io.");
        } else {
            $this->log->error("Failed to save item '{$item['title']}' to Raindrop.io.");
        }
    }

    /**
     * LINEへ通知を送信する
     *
     * @param Feed $feed
     * @param array<string, mixed> $item
     */
    private function notifyLine(Feed $feed, array $item): void
    {
        // JSTで7時から22時の間のみ通知する
        $now = $this->getCurrentTime();
        $hour = (int)$now->format('H');

        if ($hour < 7 || $hour > 22) {
            $this->log->info("Skipping LINE notification for '{$item['title']}' due to off-hours in JST.");
            return;
        }

        $this->log->info("Attempting to send LINE notification for item '{$item['title']}' for feed '{$feed->getName()}'.");
        $botId = $feed->getNotifyBot();

        if (!$botId) {
            $this->log->error("LINE notification for feed '{$feed->getName()}' is missing bot ID. Item '{$item['title']}' not notified.");
            return;
        }

        $lineConfigEnv = getenv('LINE_TOKENS_N_TARGETS');
        $lineConfig = is_string($lineConfigEnv) ? json_decode($lineConfigEnv, true) : null;

        if (!is_array($lineConfig) || !isset($lineConfig['tokens'][$botId]) || !isset($lineConfig['target_ids'][$botId])) {
            $this->log->error("LINE configuration (token or target ID) for bot '{$botId}' is missing. Item '{$item['title']}' not notified.");
            return;
        }

        if ($this->lineNotifier->notify(
            $lineConfig['tokens'][$botId],
            $lineConfig['target_ids'][$botId],
            $feed->getName(),
            $item
        )) {
            $this->log->info("Successfully sent LINE notification for item '{$item['title']}'.");
        } else {
            $this->log->error("Failed to send LINE notification for item '{$item['title']}'.");
        }
    }

    /**
     * 現在時刻を取得する (テスト時にオーバーライド可能)
     *
     * @return DateTime
     */
    protected function getCurrentTime(): DateTime
    {
        return new DateTime('now', new DateTimeZone('Asia/Tokyo'));
    }
}
