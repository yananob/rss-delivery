<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\AppConfig;
use App\FeedProcessor;
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

    // FeedProcessorをインスタンス化して処理を実行
    $feedProcessor = new FeedProcessor(
        $firestoreRepo,
        $rssParser,
        $lineNotifier,
        $raindropNotifier,
        $log
    );
    $feedProcessor->processAllFeeds();

    $log->info('Function main_event completed successfully.');
}
