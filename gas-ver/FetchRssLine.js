function FetchRssLine_main() {
  RSS_SETS = [
    // MEMO: 時間がかかってタイムアウトになることもあるので、急ぐものを前にしたほうがよい
    {
      "name": "高松小学校",
      "url": "http://swa.city-osaka.ed.jp/weblog/rss2.php?id=e711600",
      "notify_method": "LINE",
      "notify_bot": "tm_es",
      "notify_target": "tm_es",
    },
    // {
    //   "name": "文の里中学校",
    //   "url": "http://swa.city-osaka.ed.jp/weblog/rss2.php?id=j712601",
    //   "notify_method": "LINE",
    //   "notify_bot": "fs_jh",
    //   "notify_target": "fs_jh",
    // },
    // {
    //   "name": "常盤小学校",
    //   "url": "http://swa.city-osaka.ed.jp/weblog/rss2.php?id=e711601",
    //   "notify_method": "LINE",
    //   "notify_target": "stnb",
    // },
    {
      "name": "天王寺高 アンテナ",
      "url": "https://a.hatena.ne.jp/yananob/rss?gid=537560",
      "notify_method": "LINE",
      "notify_bot": "tn_hs",
      "notify_target": "tn_hs",
    },
    {
      "name": "hatena antenna",
      "url": "https://a.hatena.ne.jp/yananob/rss?gid=514163",
      "notify_method": "LINE",
      "notify_bot": "nobu",
      "notify_target": "nobu",
    },
    {
      "name": "ITS健保",
      "url": "https://www.its-kenpo.or.jp/NEWS/shisetsu/rss.xml",
      "notify_method": "LINE",
      "notify_bot": "nobu",
      "notify_target": "nobu",
    },
    
    {
     "name": "F&Mnet",
     "url": "https://www.fandmnet.com/news_release/feed",
     "notify_method": "LINE",
     "notify_bot": "nobu",
     "notify_target": "nobu",
    },
    {
     "name": "F&M",
     "url": "https://www.fmltd.co.jp/news/feed",
     "notify_method": "LINE",
     "notify_bot": "nobu",
     "notify_target": "nobu",
    },
    {
     "name": "F&M IR",
     "url": "https://www.fmltd.co.jp/ir/feed",
     "notify_method": "LINE",
     "notify_bot": "nobu",
     "notify_target": "nobu",
    },
    // item.pubDateがない
    // {
    //  "name": "OFS topics",
    //  "url": "https://www.officestation.jp/topics/rss/",
    //  "notify_method": "LINE",
    //  "notify_target": "nobu",
    // },
    {
     "name": "OFS help center",
     "url": "https://www.officestation.jp/helpcenter/info/feed/",
     "notify_method": "LINE",
     "notify_bot": "nobu",
     "notify_target": "nobu",
    },

    {
      "name": "太華動物病院",
      "url": "https://taika-ah.jp/feed/",
      "notify_method": "LINE",
      "notify_bot": "stnb",
      "notify_target": "stnb",
    },
  ];
  
  if (!Utils.isMidnight()) {
    Logger.log("It's midnight, exiting.");
    return;
  }
  
  FetchRssBase_main(this.RSS_SETS);
}
