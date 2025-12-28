<?php

declare(strict_types=1);

namespace App;

use Laminas\Feed\Reader\Reader;
use Laminas\Feed\Reader\Exception\RuntimeException;
use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * RSSフィードを解析するクラス
 */
class RssParser
{
    private Reader $reader;
    private Client $httpClient;

    /**
     * コンストラクタ
     * @param Reader|null $reader laminas-feedのReaderインスタンス
     * @param Client|null $httpClient Guzzle HTTPクライアントインスタンス
     */
    public function __construct(Reader $reader = null, Client $httpClient = null)
    {
        $this->reader = $reader ?: new Reader();
        $this->httpClient = $httpClient ?: new Client();
    }

    /**
     * 指定されたURLからRSSフィードを解析し、記事の配列を返す
     *
     * @param string $url RSSフィードのURL
     * @return array<int, array<string, mixed>> 記事データの配列
     */
    public function parse(string $url): array
    {
        try {
            $response = $this->httpClient->get($url);
            $feedContent = (string) $response->getBody();
            if (empty($feedContent)) {
                throw new RuntimeException("Failed to fetch feed content from {$url}");
            }
            return $this->parseString($feedContent);
        } catch (RequestException $e) {
            error_log('Failed to fetch RSS feed (network error): ' . $url . ' - ' . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            error_log('Failed to parse RSS feed: ' . $url . ' - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * RSSフィードの文字列を解析し、記事の配列を返す
     *
     * @param string $feedContent RSSフィードのコンテンツ文字列
     * @return array<int, array<string, mixed>> 記事データの配列
     */
    public function parseString(string $feedContent): array
    {
        try {
            $feed = $this->reader->importString($feedContent);
        } catch (RuntimeException $e) {
            // フィードの解析に失敗した場合
            error_log('Failed to parse RSS feed string: ' . $e->getMessage());
            return [];
        }

        $items = [];
        foreach ($feed as $entry) {
            $items[] = [
                'title' => $entry->getTitle(),
                'link' => $entry->getLink(),
                'description' => $this->getDescription($entry),
                'updated_at' => $this->getUpdatedAt($entry),
            ];
        }

        return $items;
    }

    /**
     * エントリから概要を取得する
     * Atomの content または summary に対応
     *
     * @param \Laminas\Feed\Reader\Entry\EntryInterface $entry
     * @return string
     */
    private function getDescription($entry): string
    {
        return $entry->getContent() ?: $entry->getDescription() ?: '';
    }

    /**
     * エントリから更新日時のタイムスタンプを取得する
     *
     * @param \Laminas\Feed\Reader\Entry\EntryInterface $entry
     * @return int|null
     */
    private function getUpdatedAt($entry): ?int
    {
        $date = $entry->getDateModified();
        if ($date instanceof DateTimeInterface) {
            return $date->getTimestamp();
        }
        return null;
    }
}
