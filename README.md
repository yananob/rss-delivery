# RSSフィード配信 Cloud Functions アプリケーション

指定されたRSSフィードを定期的に取得し、更新があった場合にLINEや指定されたWebサービス（Raindrop.io）に内容を配信するCloud Functionsアプリケーションです。

## 実装方針
- [docs/implementation_policy.md](docs/implementation_policy.md) を参照してください。

## 開発環境のセットアップ

### 前提条件
- PHP 8.4 以上
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

環境によって特定のPHP拡張機能が不足している場合は、以下を使用します。

```bash
composer install --ignore-platform-reqs
```

## テストおよび静的解析の実行

PHPUnitを使用した単体テストおよびPHPStanによる静的解析が用意されています。

### 単体テストの実行（統合テストを除く）
```bash
./vendor/bin/phpunit --exclude-group integration
```

### 統合テストの実行
Firestoreに実際に接続する統合テストは `@group integration` として分類されています。これらのテストを実行するには、Google Cloudの認証が完了しているか、Firestoreエミュレータが有効になっている必要があります。

```bash
./vendor/bin/phpunit --group integration
```
*注意: 統合テストは実際のFirestoreデータベースに対してデータの読み書きを行います。*

### 静的解析の実行 (PHPStan)
```bash
vendor/bin/phpstan analyze
```

## 設定と環境変数

| 変数名 | 説明 | 備考 |
| :--- | :--- | :--- |
| `APP_ENV` | 実行環境 (`production`, `test`, `local`) | 未設定時は `local` |
| `LINE_TOKENS_N_TARGETS` | LINE Messaging APIトークンおよびターゲットIDのJSON文字列 | BOT IDごとの `tokens` と `target_ids` |
| `RAINDROP_KEY` | Raindrop.io APIアクセストークン | Raindrop保存時に利用 |
| `FIRESTORE_EMULATOR_HOST` | Firestoreエミュレータ接続用ホスト | テスト時利用 |

### サブパスデプロイとベースパス
本アプリケーションは `APP_ENV` の設定値に基づき、自動的にベースパスを適用します (`AppConfig::getBasePath()`)。

- `production`: `/rss-delivery`
- `test`: `/rss-delivery-test`
- `local`: `` (空文字列)

## Firestore のデータ構造
本アプリケーションは、設定情報をFirestoreから読み込みます。データベースは以下の構造を想定しています。

- **ルートコレクション:** `rss-delivery` (本番環境) / `rss-delivery-test` (テスト・ローカル環境)

  - **ドキュメント:** `feeds`
    - **サブコレクション:** `feeds`
      - **ドキュメント (自動ID):**
        - `name` (string): フィード名
        - `url` (string): RSSフィードのURL
        - `notify_method` (string): `LINE` または `Save`
        - `notify_bot` (string): (LINEの場合) 使用するBOTのID
        - `enabled` (boolean): 有効/無効フラグ

  - **ドキュメント:** `updates`
    - **サブコレクション:** `updates`
      - **ドキュメント (IDはfeedsのドキュメントIDに対応):**
        - `updated_at` (string): 最終配信記事の更新日時 ('YYYY/MM/DD HH:mm:ss' 形式)
