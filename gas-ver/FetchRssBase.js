class RSSEntry {
  constructor(title, link, description, updated) {
    this.title = title;
    this.link = link;
    this.description = description;
    this.updated = updated;
  }
}

class RSSParser {
  constructor(feed_url) {
    this.feed_url = feed_url;
    this.feed_update = new FeedUpdateManager(this.feed_url);
  }

  get_new_feeds() {
    let items = this.getEntries(this.feed_url);
    this.cur_date = Date();  // save the timestamp when fetched the RSS

    let last_updated = this.feed_update.last_updated;
    if (isNaN(last_updated.valueOf())) {
      Logger.log("New feed, only updating date");
      return [];
    }

    let result = [];
    for (const item of items) {
      let item_dt = new Date(item.updated);
      if ((last_updated != null) &&
          (item_dt.valueOf() <= last_updated.valueOf())) {
        Logger.log("old item found, exiting");
        break;
      }
      result.push(item);
      if (result.length > 20) {
        Logger.log("too many results, exiting");
        break;
      }
    };
    
    return result;
  }
  
  finish() {
    this.feed_update.update(this.cur_date);
  }

  // Refer: https://imabari.hateblo.jp/entry/2015/06/18/114843
  getEntries(feed_url) {
    let response = RetryManager.execute(
      function() {
        return UrlFetchApp.fetch(feed_url);
      }
    );
    if (response == null) {
      throw `RSS fetch error: ${feed_url}`;
    }
    let feed_text = response.getContentText().trimStart();
    if (feed_url.indexOf("//leaddev.com") != -1) {
      if (feed_text.startsWith("<?xml")) {
        Logger.log("Cleaning up xml-feed for leaddev.com");
        feed_text = this.cleanup_leaddev_xmlfeed(feed_text);
      }
      else {
        Logger.log("Cleaning up html-feed for leaddev.com");
        feed_text = this.cleanup_leaddev_htmlfeed(feed_text);
      }
    }
    let xml = XmlService.parse(feed_text);
    
    let result = [];
    if (xml.getRootElement().getName() == "RDF") {
      Logger.log("RSS type: RSSv1");
      let rss = XmlService.getNamespace('http://purl.org/rss/1.0/');
      let dc = XmlService.getNamespace('dc', 'http://purl.org/dc/elements/1.1/');
      
      let items = xml.getRootElement().getChildren('item', rss);    
      for (const item of items) {
        let entry = new RSSEntry(
          item.getChild('title', rss).getText(),
          item.getChild('link', rss).getText(),
          this.pretty_format(item.getChild('description', rss).getText()),
          item.getChild('date', dc).getText()
        );
        result.push(entry);
      }
    }
    else if (xml.getRootElement().getName() == "rss") {
      Logger.log("RSS type: RSSv2");
      let items = xml.getRootElement().getChildren('channel')[0].getChildren('item');
      for (const item of items) {
        let entry = new RSSEntry(
          item.getChild('title').getText(),
          item.getChild('link').getText(),
          this.pretty_format(item.getChild('description').getText()),
          item.getChild('pubDate').getText()
        );
        result.push(entry);
      }
    }
    else if (xml.getRootElement().getName() == "feed") {
      Logger.log("RSS type: ATOM");
      let atom = XmlService.getNamespace('http://www.w3.org/2005/Atom');
      let items = xml.getRootElement().getChildren('entry', atom);
      for (const item of items) {
        let link = this.select_by_attribute(item.getChildren("link", atom), "rel", "alternate");
        let content = item.getChild('content', atom);
        if (content == null) content = item.getChild('summary', atom);
        let entry = new RSSEntry(
          item.getChild('title', atom).getText(),
          link.getAttribute('href').getValue(),
          this.pretty_format(content.getText()),
          item.getChild('updated', atom).getText()
        );
        result.push(entry);
      }
    }
    else {
      throw "Unknown rss type: " + feed_url;
    }
    
    Logger.log("Got " + result.length + " items");
    return result;
  }

  cleanup_leaddev_htmlfeed(body) {
    body = body.replace(/.+<rss/g, "<rss");                           // remove unnecessary texts
    body = body.replace(/<managingeditor.+\/managingeditor>/g, "");   // remove tag
    body = body.replace(/<description>/g, "<\/link><description>");   // add closing tag
    body = body.replace(/pubdate>/g, "pubDate>");                     // standardize tag name
    body = body.replace(/<\/rss>[\S\s]*/g, "<\/rss>");                // remove unnecessary texts

    // Logger.log(body);
    return body;
  }

  cleanup_leaddev_xmlfeed(body) {
    body = body.replace(/<category>.+<\/category>/g, "");               // remove tag

    // Logger.log(body);
    return body;
  }

  pretty_format(body) {
    body = body.replace(/<br[ \/]*>/g, "\n");
    body = body.replace(/&nbsp;/g, " ");
    body = body.replace(/<[^>]+>/g, "");
    return body;
  }
  
  select_by_attribute(elements, key, value) {
    for (let element of elements) {
      if (element.getAttribute(key) == `[${key}='${value}']`) {  // ex. [rel='alternate']
        return element;
      }
    }
    return elements[0];
  }
}

const NOTIFY_LINE = "LINE";
const NOTIFY_SAVE = "Save";

class FeedUpdateManager {
  constructor(feed_url) {
    this.feed_url = feed_url;
    this.sheet = RetryManager.execute(
      function() {
        return SpreadsheetApp.openByUrl(
          "https://docs.google.com/spreadsheets/d/1EFIt1jGvAqsj5LTjQ49zYkfKSbrOYBU84mRtp23FVqU/edit#gid=482450250").getSheetByName("RSS_Feeds");
      }      
    );
    this.row = this.find_target_row(this.feed_url);
  }
  
  find_target_row(feed_url) {
    let row;
    let textFinder = this.sheet.createTextFinder(feed_url);
    let firstOccurrence = textFinder.findNext();  // range
    if (firstOccurrence == null) {
      let a_vals = this.sheet.getRange("A1:A").getValues();
      row = a_vals.filter(String).length + 1;
      this.sheet.getRange("A" + row).setValue(feed_url);
      Logger.log("Not found, writing to new row: " + row);
    }
    else {
      Logger.log("Found existing row: " + firstOccurrence.getRow());
      row = firstOccurrence.getRow();
    };
    return row;
  }

  get last_updated() {
    let last_updated = this.sheet.getRange("B" + this.row).getValue();
    Logger.log("last_updated: " + last_updated);
    return new Date(last_updated);
  }
  
  update(updated) {
    this.sheet.getRange("B" + this.row).setValue(updated);
  }
}  

function FetchRssBase_main(rss_sets) {
  for (let rss of rss_sets) {
    Logger.log(`**** Processing [${rss["name"]}] ****`);
    let parser = new RSSParser(rss["url"]);
    entries = parser.get_new_feeds();
    // Notify from old articles
    for (let i = entries.length - 1; i >= 0; i--) {
      let entry = entries[i];
      Logger.log(`Sending: ${rss["name"]} / ${entry.title} @${entry.updated}`);
      
      msgTxt = `<${rss["name"]}>\n【${entry.title}】\n${entry.description}\n${entry.link}`;
      if (rss["notify_method"] == NOTIFY_LINE) {
        SendLINE.sendMessageV2(rss["notify_bot"], rss["notify_target"], msgTxt);
      }
      else if (rss["notify_method"] == NOTIFY_SAVE) {
        // Pocket.addItem(entry.link);
        Raindrop.addItem(entry.link);
      }
      else {
        throw new Error("Unknown notify_method: " + rss["notify_method"]);
      }
    }
    parser.finish();
  }
}
