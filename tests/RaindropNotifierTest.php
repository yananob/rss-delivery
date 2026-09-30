<?php

declare(strict_types=1);

namespace Tests;

use App\RaindropNotifier;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class RaindropNotifierTest extends TestCase
{
    public function test_Raindrop保存処理が正常に成功する(): void
    {
        $clientMock = $this->createMock(Client::class);
        $clientMock->expects($this->once())
            ->method('post')
            ->with(
                'https://api.raindrop.io/rest/v1/raindrop',
                $this->callback(function (array $options): bool {
                    $this->assertEquals('Bearer test_access_token', $options['headers']['Authorization']);
                    $this->assertEquals('https://example.com/item1', $options['json']['link']);
                    return true;
                })
            )
            ->willReturn(new Response(200));

        $notifier = new RaindropNotifier($clientMock);

        $item = [
            'title' => 'テスト記事',
            'link' => 'https://example.com/item1',
        ];

        $result = $notifier->save('test_access_token', $item);

        $this->assertTrue($result);
    }

    public function test_リンクが存在しない場合は保存処理を行わずfalseを返す(): void
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

    public function test_HTTPリクエストで例外が発生した場合にfalseを返す(): void
    {
        $clientMock = $this->createMock(Client::class);
        $clientMock->method('post')
            ->willThrowException(new RequestException('API Error', new Request('POST', 'test')));

        $notifier = new RaindropNotifier($clientMock);

        $item = [
            'title' => 'テスト記事',
            'link' => 'https://example.com/item1',
        ];

        $result = $notifier->save('test_access_token', $item);

        $this->assertFalse($result);
    }
}
