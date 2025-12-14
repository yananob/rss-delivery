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

        $lastUpdatedAt = $this->firestoreRepo->getLastUpdatedAt($feedId) ?? 0;
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

        $this->log->info(count($newItems) . " new items found for feed [{$feedName}].");

        usort($newItems, function ($a, $b) {
            return $a['updated_at'] <=> $b['updated_at'];
        });

        foreach ($newItems as $item) {
            $this->log->info("Processing new item '{$item['title']}' (Updated: {$item['updated_at']}) for feed [{$feedName}].");
            if ($feed->getNotifyMethod() === 'LINE') {
                $this->notifyLine($feed, $item);
            } elseif ($feed->getNotifyMethod() === 'Save') {
                $this->saveToRaindrop($item);
            } else {
                $this->log->warning("Unknown notification method '{$feed->getNotifyMethod()}' for feed [{$feedName}]. Item '{$item['title']}' not notified.");
            }

            $this->firestoreRepo->saveLastUpdatedAt($feedId, $item['updated_at']);
            $this->log->debug("Saved last updated timestamp ({$item['updated_at']}) for feed [{$feedName}].");
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
}
