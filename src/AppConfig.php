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
        return getenv('APP_ENV');
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
     * LINEメッセージ配信のボットID一覧を取得します。
     *
     * @return array<string> ボットIDの配列。
     */
    public static function getLineBotIds(): array
    {
        $lineConfigJson = getenv('LINE_TOKENS_N_TARGETS');
        if (!$lineConfigJson) {
            return [];
        }

        $lineConfig = json_decode($lineConfigJson, true);
        if (!isset($lineConfig['target_ids'])) {
            return [];
        }

        $ids = array_keys($lineConfig['target_ids']);
        return array_values(array_filter($ids, function ($id) {
            return !str_starts_with($id, '__');
        }));
    }
}
