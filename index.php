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

    $basePath = AppConfig::getBasePath();
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();
    $env = AppConfig::getEnvironment();

    // ベースパスを除去してルーティング用のパスを決定する
    $matchPath = $path;
    if ($basePath !== '' && strpos($path, $basePath) === 0) {
        $matchPath = substr($path, strlen($basePath));
    }
    // 空文字の場合は / に統一
    if ($matchPath === '') {
        $matchPath = '/';
    }
    // URLデコードする
    $matchPath = urldecode($matchPath);

    error_log("Request: $method $path (matchPath: $matchPath, basePath: $basePath, env: $env)");

    try {
        if ($matchPath === '/' && $method === 'GET') {
            $queryParams = $request->getQueryParams();
            $sort = $queryParams['sort'] ?? 'name';
            $direction = $queryParams['direction'] ?? 'asc';

            $feeds = $repository->getRssFeeds($sort, $direction);
            return new Response(200, [], $blade->run('index', [
                'feeds' => $feeds,
                'basePath' => $basePath,
                'currentSort' => $sort,
                'currentDirection' => $direction,
            ]));
        }

        if ($matchPath === '/new' && $method === 'GET') {
            $lineBotIds = AppConfig::getLineBotIds();
            return new Response(200, [], $blade->run('edit', [
                'feed' => null,
                'basePath' => $basePath,
                'lineBotIds' => $lineBotIds
            ]));
        }

        if ($matchPath === '/new' && $method === 'POST') {
            $params = $request->getParsedBody();
            if (empty($params['name']) || empty($params['url']) || empty($params['notify_method'])) {
                return new Response(400, [], 'Missing required parameters');
            }

            $repository->addFeed([
                'name' => $params['name'],
                'url' => $params['url'],
                'notify_method' => $params['notify_method'],
                'notify_bot' => $params['notify_bot'] ?? null,
                'enabled' => isset($params['enabled']),
            ]);
            return new Response(302, ['Location' => $basePath . '/']);
        }

        if (preg_match('#^/edit/([^/]+)$#', $matchPath, $matches) && $method === 'GET') {
            $id = $matches[1];
            $feed = $repository->getFeed($id);
            if (!$feed) {
                error_log("Feed not found: $id");
                return new Response(404, [], 'Feed not found');
            }
            $lineBotIds = AppConfig::getLineBotIds();
            return new Response(200, [], $blade->run('edit', [
                'feed' => $feed,
                'basePath' => $basePath,
                'lineBotIds' => $lineBotIds
            ]));
        }

        if (preg_match('#^/edit/([^/]+)$#', $matchPath, $matches) && $method === 'POST') {
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
                'enabled' => isset($params['enabled']),
            ]);
            return new Response(302, ['Location' => $basePath . '/']);
        }

        if (preg_match('#^/delete/([^/]+)$#', $matchPath, $matches) && $method === 'POST') {
            $id = $matches[1];
            $repository->deleteFeed($id);
            return new Response(302, ['Location' => $basePath . '/']);
        }

        error_log("Route not found: $method $path (matchPath: $matchPath)");
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
