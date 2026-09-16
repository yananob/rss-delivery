# 実装方針ガイドライン

このドキュメントは、本プロジェクト（Cloud Functions + Firestore + PHP）の設計思想、環境変数の運用、および主要な実装パターンをまとめたものです。新しい機能の追加や、同様の構成で新しいプロジェクトを開始する際の指針として利用してください。

---

## 1. アーキテクチャ概要

本アプリケーションは、Google Cloud Functions を基盤としたサーバーレスアーキテクチャを採用しています。

- **ランタイム**: PHP 8.2 以上
- **データベース**: Google Cloud Firestore (Native Mode)
- **ビュー**: BladeOne を使用してテンプレートを描画します。
- **エントリポイント (`index.php`)**:
  - `main_http`: HTTPリクエストを処理します。
  - `main_event`: Pub/Sub などのイベントを処理します。

---

## 2. 環境変数の管理

環境変数は、アプリケーションの挙動を環境（開発・テスト・本番）ごとに切り替えるために重要です。

### 主要な環境変数

| 変数名 | 説明 | 備考 |
| :--- | :--- | :--- |
| `APP_ENV` | 実行環境の指定。`production`, `test`, `development` のいずれか。 | デフォルトは `development` |
| `OPENAI_KEY_XXXX` | OpenAI API のシークレットキー。 | `XXXX` はアプリごとに変える |
| `K_SERVICE` | Cloud Functions のサービス名。 | URLの組み立てなどに使用 |

### 環境変数の取得方法

原則として直接 `getenv()` を呼び出すのではなく、`src/AppConfig.php` 等を介して取得します。これにより、デフォルト値の設定や環境ごとのロジック変更を抽象化します。

---

## 3. Firestore の初期化

Firestore へのアクセスは Application Default Credentials (ADC) や GCP 環境のデフォルト認証を使用するため、明示的なキーファイル（`FIREBASE_SERVICE_ACCOUNT` 等）の指定は不要です。

### ライブラリの初期化例

Google Cloud PHP クライアントライブラリを使用する場合、引数なしでインスタンス化します。

```php
$firestore = new FirestoreClient();
```

---

## 4. コーディング規約とルール

- **日付操作**: 必ず `Carbon\Carbon` を使用してください。
- **命名規則**:
  - PHP/JavaScript の変数・メソッド名は `camelCase`。
  - クラス名は `PascalCase`。
- **デプロイとシークレット**:
  デプロイは GitHub Actions (`.github/workflows/deploy-*.yaml`) で自動化します。機密性の高い環境変数は、GitHub Secrets に保存され、デプロイ時に Cloud Functions の環境変数として設定されます。
- 処理状況が分かるように、適宜ログを出力する。ログ出力には monolog を使う。
