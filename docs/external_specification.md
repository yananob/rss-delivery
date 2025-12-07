# RSSフィード配信アプリケーション 外部仕様書

## 1. 概要

本アプリケーションは、指定されたRSSフィードを定期的に取得し、更新があった場合にその内容をLINEや外部Webサービスに配信・保存する機能を提供する。

もともとはGoogle Apps Script（GAS）で実装されていたものを、Google Cloud Functionsへの移行を目的として再設計する。

## 2. 主な機能

### 2.1. RSSフィードの取得

-   設定ファイルに記述されたURLのRSSフィードを定期的に巡回し、新しい記事を取得する。
-   RSS 1.0, RSS 2.0, Atom形式のフィードに対応する。
-   一度取得した記事を再度配信しないよう、各フィードの最終取得日時を記録・管理する。

### 2.2. 更新内容の配信

取得した新しい記事を、設定に応じて以下のサービスに配信・保存する。

#### 2.2.1. LINEへの配信

-   記事の「フィード名」「タイトル」「概要」「リンク」を整形し、指定されたLINEアカウント（個人またはグループ）にメッセージとして送信する。

#### 2.2.2. Webサービスへの保存

-   記事のリンクを、指定されたWebサービス（Raindrop.ioなど）に保存する。

## 3. 設定方法

配信対象のRSSフィードや通知先は、設定ファイルにJSONライクな形式で記述する。

### 設定項目

| キー | 説明 | 例 |
| :--- | :--- | :--- |
| `name` | RSSフィードの名称 | `"高松小学校"` |
| `url` | RSSフィードのURL | `"http://swa.city-osaka.ed.jp/weblog/rss2.php?id=e711600"` |
| `notify_method` | 配信方法。"LINE" または "Save" を指定。 | `"LINE"` |
| `notify_bot` | （LINE配信時）通知に使用するBOTの識別子。 | `"tm_es"` |
| `notify_target` | （LINE配信時）通知先の識別子。 | `"tm_es"` |

### 設定例

#### LINE配信の設定

```javascript
{
  "name": "高松小学校",
  "url": "http://swa.city-osaka.ed.jp/weblog/rss2.php?id=e711600",
  "notify_method": "LINE",
  "notify_bot": "tm_es",
  "notify_target": "tm_es",
}
```

#### Webサービス保存の設定

```javascript
{
  "name": "メールマガジンRSS",
  "url": "https://mailmag4me.blogspot.jp/feeds/posts/default",
  "notify_method": "Save",
}
```

## 4. 実行トリガー

-   本アプリケーションは、定周期で実行されることを想定している。（例：1時間ごと）
-   GAS版では、特定の関数（`FetchRssLine_main`）に深夜帯のみ実行する制御がハードコーディングされている箇所があったが、Cloud Functions版では実行環境のタイマー機能で制御することを想定する。

## 5. 依存外部サービス

-   Google Sheets: 各RSSフィードの最終取得日時を管理するために使用。
-   LINE Messaging API: LINEへメッセージを送信するために使用。
-   Raindrop.io API: Webサービスへリンクを保存するために使用。
