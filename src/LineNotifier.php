<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * LINEに通知を送信するクラス
 */
class LineNotifier
{
    private const API_ENDPOINT = 'https://api.line.me/v2/bot/message/push';
    private Client $httpClient;

    /**
     * コンストラクタ
     * @param Client|null $httpClient Guzzle HTTP Client
     */
    public function __construct(Client $httpClient = null)
    {
        $this->httpClient = $httpClient ?: new Client();
    }

    /**
     * LINEにメッセージを送信する
     *
     * @param string $channelAccessToken LINE Channel Access Token
     * @param string $targetId 送信先のユーザーIDまたはグループID
     * @param string $feedName フィード名
     * @param array<string, mixed> $item 記事情報
     * @return bool 送信が成功したかどうか
     */
    public function notify(string $channelAccessToken, string $targetId, string $feedName, array $item): bool
    {
        $message = $this->buildMessage($feedName, $item);

        try {
            $response = $this->httpClient->post(self::API_ENDPOINT, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $channelAccessToken,
                ],
                'json' => [
                    'to' => $targetId,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => $message,
                        ],
                    ],
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            error_log('Failed to send LINE notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 送信するメッセージ文字列を組み立てる
     *
     * @param string $feedName
     * @param array<string, mixed> $item
     * @return string
     */
    private function buildMessage(string $feedName, array $item): string
    {
        $title = html_entity_decode(strip_tags($item['title'] ?? '（タイトルなし）'));
        $description = $this->truncateDescription($item['description'] ?? '');
        $link = $item['link'] ?? '';

        return <<<EOT
{$feedName}
【{$title}】
{$description}
{$link}
EOT;
    }

    /**
     * 概要を指定された文字数で切り詰め、HTMLタグを除去し、HTMLエンティティをデコードする
     *
     * @param string $description
     * @param int $length
     * @return string
     */
    private function truncateDescription(string $description, int $length = 200): string
    {
        // HTMLタグを除去し、HTMLエンティティをデコード
        $description = html_entity_decode(strip_tags($description));
        if (mb_strlen($description) > $length) {
            return mb_substr($description, 0, $length) . '...';
        }
        return $description;
    }
}
