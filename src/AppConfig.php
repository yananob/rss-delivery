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
        return getenv('APP_ENV') ?: 'local';
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
}
