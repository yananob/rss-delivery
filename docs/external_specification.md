# RSSフィード配信アプリケーション 外部仕様書

## 1. 概要

本アプリケーションは、指定されたRSSフィードを定期的に取得し、更新があった場合にその内容をLINEや外部Webサービスに配信・保存する機能を提供する。

もともとはGoogle Apps Script（GAS）で実装されていたものを、Google Cloud Functionsへの移行を目的として再設計する。

## 2. 主な機能

### 2.1. RSSフィードの取得

-   設定に記述されたURLのRSSフィードを定期的に巡回し、新しい記事を取得する。
-   RSS 1.0, RSS 2.0, Atom形式のフィードに対応する。
-   一度取得した記事を再度配信しないよう、各フィードの最終取得日時を記録・管理する。

#### RSS形式の判別

取得したXMLのルート要素名によって、以下の通りRSSの形式を判別する。

-   `RDF`: RSS 1.0
-   `rss`: RSS 2.0
-   `feed`: Atom

#### 取得フィールド

RSSの形式ごとに、以下のフィールドを取得して内部データとして利用する。

| RSS形式 | 記事タイトル | 記事リンク | 記事概要 | 更新日時 |
| :--- | :--- | :--- | :--- | :--- |
| **RSS 1.0** | `title` | `link` | `description` | `dc:date` |
| **RSS 2.0** | `title` | `link` | `description` | `pubDate` |
| **Atom** | `title` | `link` (rel="alternate") | `content` または `summary` | `updated` |

### 2.2. 更新内容の配信

取得した新しい記事を、設定に応じて以下のサービスに配信・保存する。

#### 2.2.1. LINEへの配信

-   記事の「フィード名」「タイトル」「概要」「リンク」を整形し、指定されたLINEアカウント（個人またはグループ）にメッセージとして送信する。
-   メッセージは以下の形式で組み立てられる。

```
<フィード名>
【記事タイトル】
記事概要
記事リンク
```

#### 2.2.2. Webサービスへの保存

-   記事のリンクを、指定されたWebサービス（Raindrop.ioなど）に保存する。

## 3. 設定方法

配信対象のRSSフィードや通知先は、**Firestore**の特定のコレクションからドキュメントとして取得する。

### 設定項目 (Firestoreドキュメントのフィールド)

| フィールド名 | 説明 | 例 |
| :--- | :--- | :--- |
| `name` | RSSフィードの名称 | `"サンプルフィード"` |
| `url` | RSSフィードのURL | `"https://example.com/rss.xml"` |
| `notify_method` | 配信方法。"LINE" または "Save" を指定。 | `"LINE"` |
| `notify_bot` | （LINE配信時）通知に使用するBOTの識別子。 | `"bot_A"` |
| `notify_target` | （LINE配信時）通知先の識別子。 | `"target_X"` |

### 設定例 (Firestore)

**コレクション:** `rss_settings`

**ドキュメントID:** (自動生成ID or 任意のID)

#### LINE配信用のドキュメント

```json
{
  "name": "サンプルフィード",
  "url": "https://example.com/rss.xml",
  "notify_method": "LINE",
  "notify_bot": "bot_A",
  "notify_target": "target_X"
}
```

#### Webサービス保存用のドキュメント

```json
{
  "name": "技術ブログRSS",
  "url": "https://tech.example.com/feed",
  "notify_method": "Save"
}
```

## 4. 実行トリガー

-   本アプリケーションは、定周期で実行されることを想定している。（例：1時間ごと）
-   GAS版では、特定の関数（`FetchRssLine_main`）に深夜帯のみ実行する制御がハードコーディングされている箇所があったが、Cloud Functions版では実行環境のタイマー機能で制御することを想定する。

## 5. 依存外部サービス

-   **Firestore**:
    -   配信設定の管理
    -   各RSSフィードの最終取得日時の管理
-   **LINE Messaging API**: LINEへメッセージを送信するために使用。
-   **Raindrop.io API**: Webサービスへリンクを保存するために使用。
