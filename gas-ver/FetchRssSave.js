function FetchRssSave_main() {
  RSS_SETS = [
    // {
    //   "name": "Life is Beautiful",
    //   "url": "https://satoshi.blogs.com/life/atom.xml",
    //   "notify_method": NOTIFY_SAVE,
    // },
    {
      "name": "メールマガジンRSS",
      "url": "https://mailmag4me.blogspot.jp/feeds/posts/default",
      "notify_method": NOTIFY_SAVE,
    },
    {
      "name": "タイム・コンサルタントの日誌から",
      "url": "https://brevis.exblog.jp/index.xml",
      "notify_method": NOTIFY_SAVE,
    },
    {
      "name": "ユーモアコミュニケーション",
      "url": "https://marthakusakari.com/blog/feed",
      "notify_method": NOTIFY_SAVE,
    },
    // {
    //   "name": "設計者の発言",
    //   "url": "https://dbconcept.hatenablog.com/rss",
    //   "notify_method": NOTIFY_SAVE,
    // },
    {
      "name": "ゴゴログ",
      "url": "https://gogotomo.ldblog.jp/index.rdf",
      "notify_method": NOTIFY_SAVE,
    },
    {
      "name": "小さなごちそう",
      "url": "https://tannomizuki.hatenablog.com/feed",
      "notify_method": NOTIFY_SAVE,
    },
    // DOLでフォローしてメールにした
    // {
    //   "name": "三谷流構造的やわらか発想法",
    //   "url": "https://diamond.jp/list/rss?cc=s-mitani",
    //   "notify_method": NOTIFY_SAVE,
    // },
    // {
    //   "name": "The Pragmatic Engineer",
    //   "url": "https://blog.pragmaticengineer.com/rss/",
    //   "notify_method": NOTIFY_SAVE,
    // },
    // {
    //   "name": "LeadDev",
    //   "url": "https://leaddev.com/content-piece-and-series/rss.xml",
    //   "notify_method": NOTIFY_SAVE,
    // },
  ];
  
  FetchRssBase_main(this.RSS_SETS);
}
