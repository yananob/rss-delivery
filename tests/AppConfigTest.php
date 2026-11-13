<?php

declare(strict_types=1);

namespace Tests;

use App\AppConfig;
use PHPUnit\Framework\TestCase;

class AppConfigTest extends TestCase
{
    public function test_getLineBotIds_returns_correct_ids(): void
    {
        $original = getenv('LINE_TOKENS_N_TARGETS');
        $testConfig = json_encode([
            'tokens' => ['bot1' => 't1', 'bot2' => 't2', '__hidden' => 't3'],
            'target_ids' => ['bot1' => 'id1', 'bot2' => 'id2', '__hidden' => 'id3']
        ]);
        putenv("LINE_TOKENS_N_TARGETS=$testConfig");

        $botIds = AppConfig::getLineBotIds();

        $this->assertCount(2, $botIds);
        $this->assertContains('bot1', $botIds);
        $this->assertContains('bot2', $botIds);
        $this->assertNotContains('__hidden', $botIds);

        putenv("LINE_TOKENS_N_TARGETS=$original");
    }

    public function test_getLineBotIds_returns_empty_array_when_env_not_set(): void
    {
        $original = getenv('LINE_TOKENS_N_TARGETS');
        putenv('LINE_TOKENS_N_TARGETS'); // unset

        $botIds = AppConfig::getLineBotIds();

        $this->assertEmpty($botIds);

        putenv("LINE_TOKENS_N_TARGETS=$original"); // restore
    }

    public function test_getLineBotIds_returns_empty_array_when_invalid_json(): void
    {
        $original = getenv('LINE_TOKENS_N_TARGETS');
        putenv('LINE_TOKENS_N_TARGETS=invalid-json');

        $botIds = AppConfig::getLineBotIds();

        $this->assertEmpty($botIds);

        putenv("LINE_TOKENS_N_TARGETS=$original");
    }

    public function test_getEnvironment_defaults_to_local(): void
    {
        $original = getenv('APP_ENV');
        putenv('APP_ENV'); // unset

        $env = AppConfig::getEnvironment();
        $this->assertEquals('local', $env);

        putenv('APP_ENV='); // empty string
        $this->assertEquals('local', AppConfig::getEnvironment());

        putenv('APP_ENV=production');
        $this->assertEquals('production', AppConfig::getEnvironment());

        if ($original !== false) {
            putenv("APP_ENV=$original");
        } else {
            putenv('APP_ENV');
        }
    }
}
