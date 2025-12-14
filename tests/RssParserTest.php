<?php

declare(strict_types=1);
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

    /**
     * @test
     */
    public function test_RSS_1_0フィードを正しく解析できる(): void
    {
        $rdfContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel rdf:about="https://example.com/">
    <title>テストフィード RDF</title>
    <link>https://example.com/</link>
    <description>RDF形式のテスト</description>
  </channel>
  <item rdf:about="https://example.com/rdf_entry1">
    <title>RDF記事1</title>
    <link>https://example.com/rdf_entry1</link>
    <description>RDFの概要1</description>
    <dc:date>2023-01-03T12:00:00Z</dc:date>
  </item>
</rdf:RDF>
XML;

        $parser = new RssParser();
        $result = $parser->parseString($rdfContent);

        $this->assertCount(1, $result);
        $this->assertEquals('RDF記事1', $result[0]['title']);
        $this->assertEquals('https://example.com/rdf_entry1', $result[0]['link']);
        $this->assertEquals('RDFの概要1', $result[0]['description']);
        $this->assertEquals(strtotime('2023-01-03T12:00:00Z'), $result[0]['updated_at']);
    }

    /**
     * @test
     */
    public function test_Atomフィードを正しく解析できる(): void
    {
        $atomContent = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>テストフィード Atom</title>
  <link href="https://example.com/"/>
  <updated>2023-01-04T12:00:00Z</updated>
  <entry>
    <title>Atom記事1</title>
    <link href="https://example.com/atom_entry1"/>
    <id>urn:uuid:1225c695-cfb8-4ebb-aaaa-80da344efa6a</id>
    <updated>2023-01-04T12:00:00Z</updated>
    <summary>Atomの概要</summary>
    <content type="html">Atomの本文</content>
  </entry>
</feed>
XML;

        $parser = new RssParser();
        $result = $parser->parseString($atomContent);

        $this->assertCount(1, $result);
        $this->assertEquals('Atom記事1', $result[0]['title']);
        $this->assertEquals('https://example.com/atom_entry1', $result[0]['link']);
        $this->assertEquals('Atomの本文', $result[0]['description']); // summaryよりcontentが優先される
        $this->assertEquals(strtotime('2023-01-04T12:00:00Z'), $result[0]['updated_at']);
    }
}
