<?php

declare(strict_types=1);

namespace Tests;

use App\AppConfig;
use PHPUnit\Framework\TestCase;

class AppConfigTest extends TestCase
{
    public function test_getLineBotIds_returns_correct_ids(): void
    {
        // phpunit.xml で設定されている環境変数を使用
        // LINE_TOKENS_N_TARGETS = '{"tokens":{"test_bot":"dummy_token"},"target_ids":{"test_bot":"dummy_target"}}'

        $botIds = AppConfig::getLineBotIds();

        $this->assertIsArray($botIds);
        $this->assertCount(1, $botIds);
        $this->assertEquals(['test_bot'], $botIds);
    }

    public function test_getLineBotIds_returns_empty_array_when_env_not_set(): void
    {
        $original = getenv('LINE_TOKENS_N_TARGETS');
        putenv('LINE_TOKENS_N_TARGETS'); // unset

        $botIds = AppConfig::getLineBotIds();

        $this->assertIsArray($botIds);
        $this->assertEmpty($botIds);

        putenv("LINE_TOKENS_N_TARGETS=$original"); // restore
    }
}
