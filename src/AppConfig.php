<?php

declare(strict_types=1);

namespace App;

/**
 * アプリケーション環境に基づいて設定値を提供します。
 * 環境は`APP_ENV`環境変数によって決定されます。
 *
 * サポートされる環境: 'production', 'test', 'local'。
 * `APP_ENV`が設定されていない場合、デフォルトは'local'です。
 */
class AppConfig
{
    /**
     * 現在のアプリケーション環境を取得します。
     *
     * @return string 現在の環境 ('production', 'test', または 'local')。
     */
    public static function getEnvironment(): string
    {
        $env = getenv('APP_ENV');
        return is_string($env) ? $env : 'local';
    }

    /**
     * Firestoreのルートコレクション名を取得します。
     *
     * @return string Firestoreコレクションの名前。
     */
    public static function getFirestoreRootCollection(): string
    {
        return match (self::getEnvironment()) {
            'production' => 'rss-delivery',
            'test', => 'rss-delivery-test',
            default => 'rss-delivery-test',
        };
    }

    /**
     * LINEメッセージ配信のターゲットとなるユーザー/グループIDを取得します。
     *
     * @return string LINEターゲットID。
     */
    public static function getLineDeliverTarget(): string
    {
        return match (self::getEnvironment()) {
            // 'production' => '',
            'test' => 'nobu',
            default => 'nobu',
        };
    }

    /**
     * アプリケーションのベースパスを取得します。
     *
     * @return string ベースパス。
     */
    public static function getBasePath(): string
    {
        return match (self::getEnvironment()) {
            'production' => '/rss-delivery',
            'test' => '/rss-delivery-test',
            default => '',
        };
    }

    /**
     * LINEメッセージ配信のボットID一覧を取得します。
     *
     * @return array<string> ボットIDの配列。
     */
    public static function getLineBotIds(): array
    {
        $lineConfigJson = getenv('LINE_TOKENS_N_TARGETS');
        if (!is_string($lineConfigJson)) {
            return [];
        }

        $lineConfig = json_decode($lineConfigJson, true);
        if (!is_array($lineConfig) || !isset($lineConfig['target_ids']) || !is_array($lineConfig['target_ids'])) {
            return [];
        }

        $ids = array_keys($lineConfig['target_ids']);
        $botIds = [];
        foreach ($ids as $id) {
            if (is_string($id) && !str_starts_with($id, '__')) {
                $botIds[] = $id;
            }
        }
        return $botIds;
    }
}
