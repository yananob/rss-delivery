<?php

namespace Tests;

use App\RssParser;
use PHPUnit\Framework\TestCase;

class RssParserTest extends TestCase
{
    /**
     * @test
     */
    public function test_RSS_2_0フィードを正しく解析できる(): void
    {
        $rssContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
<channel>
  <title>テストフィード</title>
  <link>https://example.com/</link>
  <description>これはテスト用のRSSフィードです。</description>
  <item>
    <title>記事タイトル1</title>
    <link>https://example.com/entry1</link>
    <description><![CDATA[記事の概要1]]></description>
    <pubDate>Sun, 01 Jan 2023 12:00:00 +0000</pubDate>
  </item>
  <item>
    <title>記事タイトル2</title>
    <link>https://example.com/entry2</link>
    <description><![CDATA[記事の概要2]]></description>
    <pubDate>Mon, 02 Jan 2023 12:00:00 +0000</pubDate>
  </item>
</channel>
</rss>
XML;

        $parser = new RssParser();
        $result = $parser->parseString($rssContent);

        $this->assertCount(2, $result);

        $this->assertEquals('記事タイトル1', $result[0]['title']);
        $this->assertEquals('https://example.com/entry1', $result[0]['link']);
        $this->assertEquals('記事の概要1', $result[0]['description']);
        $this->assertEquals(strtotime('2023-01-01 12:00:00'), $result[0]['updated_at']);

        $this->assertEquals('記事タイトル2', $result[1]['title']);
        $this->assertEquals('https://example.com/entry2', $result[1]['link']);
        $this->assertEquals('記事の概要2', $result[1]['description']);
        $this->assertEquals(strtotime('2023-01-02 12:00:00'), $result[1]['updated_at']);
    }
}
