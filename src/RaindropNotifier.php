<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Raindrop.ioに通知（保存）するクラス
 */
class RaindropNotifier
{
    private const API_ENDPOINT = 'https://api.raindrop.io/rest/v1/raindrop';
    private Client $httpClient;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->httpClient = new Client();
    }

    /**
     * Raindrop.ioにリンクを保存する
     *
     * @param string $accessToken Raindrop.io API Access Token
     * @param array<string, mixed> $item 記事情報
     * @return bool 保存が成功したかどうか
     */
    public function save(string $accessToken, array $item): bool
    {
        $link = $item['link'] ?? null;
        if (!$link) {
            return false;
        }

        try {
            $response = $this->httpClient->post(self::API_ENDPOINT, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => [
                    'link' => $link,
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            error_log('Failed to save to Raindrop.io: ' . $e->getMessage());
            return false;
        }
    }
}
