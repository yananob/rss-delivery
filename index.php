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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use eftec\bladeone\BladeOne;
use CloudEvents\V1\CloudEventInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Cloud Functionsのエントリポイントを登録
FunctionsFramework::cloudEvent('main_event', 'main_event');
FunctionsFramework::http('main_http', 'main_http');

/**
 * HTTPリクエストを処理してUIを表示・操作する
 *
 * @param ServerRequestInterface $request
 * @return ResponseInterface
 */
function main_http(ServerRequestInterface $request): ResponseInterface
{
    $views = __DIR__ . '/views';
    // Cloud Functions環境では /tmp しか書き込みできない場合がある
    $cache = sys_get_temp_dir() . '/blade_cache';
    if (!is_dir($cache)) {
        mkdir($cache, 0777, true);
    }

    $mode = AppConfig::getEnvironment() === 'production' ? BladeOne::MODE_AUTO : BladeOne::MODE_DEBUG;
    $blade = new BladeOne($views, $cache, $mode);
    $repository = new FirestoreRepository();

    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    try {
        if ($path === '/' && $method === 'GET') {
            $feeds = $repository->getRssFeeds();
            return new Response(200, [], $blade->run('index', ['feeds' => $feeds]));
        }

        if ($path === '/new' && $method === 'GET') {
            return new Response(200, [], $blade->run('edit', ['feed' => null]));
        }

        if ($path === '/new' && $method === 'POST') {
            $params = $request->getParsedBody();
            if (empty($params['name']) || empty($params['url']) || empty($params['notify_method'])) {
                return new Response(400, [], 'Missing required parameters');
            }

            $repository->addFeed([
                'name' => $params['name'],
                'url' => $params['url'],
                'notify_method' => $params['notify_method'],
                'notify_bot' => $params['notify_bot'] ?? null,
            ]);
            return new Response(302, ['Location' => '/']);
        }

        if (preg_match('#^/edit/([^/]+)$#', $path, $matches) && $method === 'GET') {
            $id = $matches[1];
            $feed = $repository->getFeed($id);
            if (!$feed) {
                return new Response(404, [], 'Feed not found');
            }
            return new Response(200, [], $blade->run('edit', ['feed' => $feed]));
        }

        if (preg_match('#^/edit/([^/]+)$#', $path, $matches) && $method === 'POST') {
            $id = $matches[1];
            $params = $request->getParsedBody();
            if (empty($params['name']) || empty($params['url']) || empty($params['notify_method'])) {
                return new Response(400, [], 'Missing required parameters');
            }

            $repository->updateFeed($id, [
                'name' => $params['name'],
                'url' => $params['url'],
                'notify_method' => $params['notify_method'],
                'notify_bot' => $params['notify_bot'] ?? null,
            ]);
            return new Response(302, ['Location' => '/']);
        }

        if (preg_match('#^/delete/([^/]+)$#', $path, $matches) && $method === 'POST') {
            $id = $matches[1];
            $repository->deleteFeed($id);
            return new Response(302, ['Location' => '/']);
        }

        return new Response(404, [], 'Not Found');
    } catch (\Exception $e) {
        $errorMessage = AppConfig::getEnvironment() === 'production' ? 'Internal Server Error' : 'Error: ' . $e->getMessage();
        return new Response(500, [], $errorMessage);
    }
}

/**
 * Pub/SubイベントをトリガーにRSSフィードを取得し、更新があれば通知する
 *
 * @param CloudEventInterface $event
 * @return void
 */
function main_event(CloudEventInterface $event): void
{
    $log = new Logger('main_event_logger');
    $log->pushHandler(new StreamHandler('php://stderr', Logger::INFO));
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
