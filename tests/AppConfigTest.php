<?php

declare(strict_types=1);

namespace Tests;

use App\AppConfig;
use PHPUnit\Framework\TestCase;

class AppConfigTest extends TestCase
{
    public function test_getEnvironment_returns_local_by_default(): void
    {
        $original = getenv('APP_ENV');
        putenv('APP_ENV');

        $env = AppConfig::getEnvironment();
        $this->assertEquals('local', $env);

        putenv($original !== false ? "APP_ENV=$original" : 'APP_ENV');
    }

    public function test_getEnvironment_returns_configured_env(): void
    {
        $original = getenv('APP_ENV');
        putenv('APP_ENV=production');

        $env = AppConfig::getEnvironment();
        $this->assertEquals('production', $env);

        putenv($original !== false ? "APP_ENV=$original" : 'APP_ENV');
    }

    public function test_getBasePath_returns_correct_path_for_environments(): void
    {
        $original = getenv('APP_ENV');

        putenv('APP_ENV=production');
        $this->assertEquals('/rss-delivery', AppConfig::getBasePath());

        putenv('APP_ENV=test');
        $this->assertEquals('/rss-delivery-test', AppConfig::getBasePath());

        putenv('APP_ENV=local');
        $this->assertEquals('', AppConfig::getBasePath());

        putenv($original !== false ? "APP_ENV=$original" : 'APP_ENV');
    }

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

        putenv($original !== false ? "LINE_TOKENS_N_TARGETS=$original" : 'LINE_TOKENS_N_TARGETS');
    }

    public function test_getLineBotIds_returns_empty_array_when_env_not_set(): void
    {
        $original = getenv('LINE_TOKENS_N_TARGETS');
        putenv('LINE_TOKENS_N_TARGETS');

        $botIds = AppConfig::getLineBotIds();

        $this->assertEmpty($botIds);

        putenv($original !== false ? "LINE_TOKENS_N_TARGETS=$original" : 'LINE_TOKENS_N_TARGETS');
    }
}
