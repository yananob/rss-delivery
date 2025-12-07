**Description**

RSSを取得して、更新内容をLINEやWebサービスに配信するアプリのGAS版コード。
今後GAS版からcloud function版にしたい。

- gas版の説明：（gas-ver ディレクトリ）
  - FetchRssBase.js: Rss取得&配信の基本処理。いろんな形式のrssに対応。最終取得日時はスプレッドシートに保存。
  - FetchRssLine.js: rssを取得してLINEに配信する具体的な対象。
  - FetchRssSave.js: rssを取得してWebサービスに登録する具体的な対象。
