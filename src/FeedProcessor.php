<?php

declare(strict_types=1);

namespace App;

use Monolog\Logger;

class FeedProcessor
{
    public function __construct(
        private FirestoreRepository $firestoreRepo,
        private RssParser $rssParser,
        private LineNotifier $lineNotifier,
        private RaindropNotifier $raindropNotifier,
        private Logger $log
    ) {
    }

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
            $this->log->debug('Processing single feed: ' . $feed->getName() . ' (ID: ' . $feed->getId() . ')');
            $this->processSingleFeed($feed);
            $this->log->debug('Finished processing feed: ' . $feed->getName());
        }
    }

    private function processSingleFeed(Feed $feed): void
    {
        $feedId = $feed->getId();
        $feedUrl = $feed->getUrl();
        $feedName = $feed->getName();
        $this->log->info("Processing feed: [{$feedName}] (ID: {$feedId}, URL: {$feedUrl})");

        // lastUpdatedAtがnullの場合、初回実行と判断する
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

        $newItems = array_filter($items, function ($item) use ($lastUpdatedAt) {
            return isset($item['updated_at']) && $item['updated_at'] > $lastUpdatedAt;
        });

        if (empty($newItems)) {
            $this->log->info("No new items found for feed [{$feedName}]. Skipping.");
            return;
        }

        $newItemsCount = count($newItems);
        $this->log->info("{$newItemsCount} new items found for feed [{$feedName}].");

        usort($newItems, function ($a, $b) {
            return $a['updated_at'] <=> $b['updated_at'];
        });

        // 常に最新の記事の日時を保存する
        $latestItemTimestamp = end($newItems)['updated_at'];
        $this->firestoreRepo->saveLastUpdatedAt($feedId, $latestItemTimestamp);
        $this->log->debug("Saved last updated timestamp ({$latestItemTimestamp}) for feed [{$feedName}].");

        // 初回実行時は通知をスキップ
        if ($isFirstRun) {
            $this->log->info("First run for feed '{$feedName}'. Skipping notification process.");
            return;
        }

        // 新規アイテムが20件を超える場合は通知をスキップ
        if ($newItemsCount > 20) {
            $this->log->info("Too many new items ({$newItemsCount}) for feed '{$feedName}'. Skipping notification process to avoid flooding.");
            return;
        }

        foreach ($newItems as $item) {
            $this->log->info("Processing new item '{$item['title']}' (Updated: {$item['updated_at']}) for feed [{$feedName}].");
            if ($feed->getNotifyMethod() === 'LINE') {
                $this->notifyLine($feed, $item);
            } elseif ($feed->getNotifyMethod() === 'Save') {
                $this->saveToRaindrop($item);
            } else {
                $this->log->warning("Unknown notification method '{$feed->getNotifyMethod()}' for feed [{$feedName}]. Item '{$item['title']}' not notified.");
            }
        }
        $this->log->info("Finished processing all new items for feed [{$feedName}].");
    }

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

        $lineConfig = json_decode(getenv('LINE_TOKENS_N_TARGETS'), true);

        if (!isset($lineConfig['tokens'][$botId]) || !isset($lineConfig['target_ids'][$botId])) {
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

    protected function getCurrentTime(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone('Asia/Tokyo'));
    }
}
