<?php

declare(strict_types=1);

namespace Tests;

use App\RaindropNotifier;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

class RaindropNotifierTest extends TestCase
{
    public function test_Raindropにリンクが正常に保存される(): void
    {
        $clientMock = $this->createMock(Client::class);
        $clientMock->expects($this->once())
            ->method('post')
            ->with(
                'https://api.raindrop.io/rest/v1/raindrop',
                $this->callback(function ($options) {
                    $this->assertEquals('Bearer test_access_token', $options['headers']['Authorization']);
                    $this->assertEquals('application/json', $options['headers']['Content-Type']);
                    $this->assertEquals('https://example.com/article', $options['json']['link']);
                    $this->assertInstanceOf(\stdClass::class, $options['json']['pleaseParse']);
                    return true;
                })
            )
            ->willReturn(new Response(200));

        $notifier = new RaindropNotifier($clientMock);
        $item = [
            'title' => 'テスト記事',
            'link' => 'https://example.com/article',
        ];

        $result = $notifier->save('test_access_token', $item);
        $this->assertTrue($result);
    }

    public function test_リンクが存在しない場合は保存をスキップしてfalseを返す(): void
    {
        $clientMock = $this->createMock(Client::class);
        $clientMock->expects($this->never())->method('post');

        $notifier = new RaindropNotifier($clientMock);
        $item = [
            'title' => 'リンクなし記事',
        ];

        $result = $notifier->save('test_access_token', $item);
        $this->assertFalse($result);
    }

    public function test_APIリクエストが失敗した場合はfalseを返す(): void
    {
        $clientMock = $this->createMock(Client::class);
        $clientMock->method('post')
            ->willThrowException(new RequestException('Network error', new Request('POST', 'https://api.raindrop.io/rest/v1/raindrop')));

        $notifier = new RaindropNotifier($clientMock);
        $item = [
            'link' => 'https://example.com/article',
        ];

        $result = $notifier->save('test_access_token', $item);
        $this->assertFalse($result);
    }
}
