<?php

declare(strict_types=1);
namespace Tests;

use App\LineNotifier;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

class LineNotifierTest extends TestCase
{
    public function test_LINE通知が正しく送信される(): void
    {
        // GuzzleのClientをモック
        $clientMock = $this->createMock(Client::class);
        $clientMock->expects($this->once())
            ->method('post')
            ->with(
                'https://api.line.me/v2/bot/message/push',
                $this->callback(function ($options) {
                    $this->assertEquals('Bearer test_token', $options['headers']['Authorization']);
                    $this->assertEquals('test_target_id', $options['json']['to']);
                    $this->assertStringContainsString('テストフィード', $options['json']['messages'][0]['text']);
                    $this->assertStringContainsString('【テスト記事】', $options['json']['messages'][0]['text']);
                    return true;
                })
            )
            ->willReturn(new Response(200));

        $notifier = new LineNotifier($clientMock);

        $item = [
            'title' => 'テスト記事',
            'description' => 'これはテストです。',
            'link' => 'https://example.com/test'
        ];

        $result = $notifier->notify('test_token', 'test_target_id', 'テストフィード', $item);

        $this->assertTrue($result);
    }

    public function test_LINE通知が失敗したときにfalseを返す(): void
    {
        // GuzzleのClientをモックし、例外をスローさせる
        $clientMock = $this->createMock(Client::class);
        $clientMock->method('post')
            ->willThrowException(new RequestException('Error Communicating with Server', new Request('POST', 'test')));

        $notifier = new LineNotifier($clientMock);

        $item = ['title' => 'a', 'description' => 'b', 'link' => 'c'];
        $result = $notifier->notify('token', 'id', 'feed', $item);

        $this->assertFalse($result);
    }

    public function test_HTMLタグとエンティティが正しく処理される(): void
    {
        // GuzzleのClientをモック
        $clientMock = $this->createMock(Client::class);
        $clientMock->expects($this->once())
            ->method('post')
            ->with(
                'https://api.line.me/v2/bot/message/push',
                $this->callback(function ($options) {
                    $expectedMessage = <<<EOT
テストフィード
【Test & Title】
This is a description with a link & some bold text.
https://example.com/test-html
EOT;
                    $this->assertEquals($expectedMessage, $options['json']['messages'][0]['text']);
                    return true;
                })
            )
            ->willReturn(new Response(200));

        $notifier = new LineNotifier($clientMock);

        $item = [
            'title' => '<b>Test &amp; Title</b>',
            'description' => '<p>This is a description with a <a href="#">link</a> &amp; some bold text.</p>',
            'link' => 'https://example.com/test-html'
        ];

        $result = $notifier->notify('test_token', 'test_target_id', 'テストフィード', $item);

        $this->assertTrue($result);
    }
}
