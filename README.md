# RSSフィード配信 Cloud Functions アプリケーション

指定されたRSSフィードを定期的に取得し、更新があった場合にLINEや指定されたWebサービスに内容を配信するCloud Functionsアプリケーションです。

## 開発環境のセットアップ

### 前提条件
- PHP 8.3 以上
- Composer
- Google Cloud SDK (テストでFirestoreに接続する場合)

### 必要なPHP拡張機能
Ubuntu環境の場合、以下のコマンドで必要な拡張機能をインストールできます。

```bash
# PPAを追加してPHPのバージョンを管理
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update

# 必要なパッケージをインストール
sudo apt-get install php-cli php-mbstring php-curl php-grpc
```

### 依存関係のインストール
プロジェクトのルートディレクトリで以下のコマンドを実行し、Composerを使って依存関係をインストールします。

```bash
composer install
```

## テストの実行
PHPUnitを使用したテストが用意されています。

### すべてのテストを実行
```bash
./vendor/bin/phpunit
```

### 統合テストのみ実行
Firestoreに実際に接続する統合テストは `@group integration` として分類されています。これらのテストを実行するには、Google Cloudの認証が完了しており、プロジェクトIDが環境に設定されている必要があります。

```bash
./vendor/bin/phpunit --group integration
```
*注意: 統合テストは実際のFirestoreデータベースに対してデータの読み書きを行います。*

## Firestore のデータ構造
本アプリケーションは、設定情報をFirestoreから読み込みます。データベースは以下の構造を想定しています。

- **ルートコレクション:** `rss-delivery`

  - **ドキュメント:** `rss_feeds`
    - **サブコレクション:** `rss_feeds`
      - **ドキュメント (自動ID):**
        - `name` (string): フィード名
        - `url` (string): RSSフィードのURL
        - `notify_method` (string): `LINE` または `Save`
        - `notify_bot` (string): (LINEの場合) 使用するBOTのID
        - `notify_target` (string): (LINEの場合) 送信先のID

  - **ドキュメント:** `updates`
    - **サブコレクション:** `updates`
      - **ドキュメント (IDはrss_feedsのドキュメントIDに対応):**
        - `updated_at` (timestamp): 最終配信記事の更新日時

  - **ドキュメント:** `line_bots`
    - **サブコレクション:** `line_bots`
      - **ドキュメント (IDは `notify_bot` の値に対応):**
        - `access_token` (string): LINE Messaging APIのアクセストークン

  - **ドキュメント:** `raindrop_configs`
    - **サブコレクション:** `raindrop_configs`
      - **ドキュメント (IDは `main` で固定):**
        - `access_token` (string): Raindrop.io のAPIアクセストークン
