<?php

declare(strict_types=1);

namespace Rekhu\Core\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Rekhu\Core\Tests\TestCase;

final class CommandsTest extends TestCase
{
    private string $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = base_path('.env');
        file_put_contents($this->env, "APP_NAME=Portal\n");
        Http::fake(['rekhu.test/v1/knowers' => Http::response(['knowers' => [['knower' => 'machines', 'name' => 'Rekhu Machines', 'description' => 'Knows machines', 'actions' => [['action' => 'identify', 'description' => 'Which model']]]]])]);
    }

    protected function tearDown(): void
    {
        @unlink($this->env);
        parent::tearDown();
    }

    public function test_install_connects_and_writes_the_settings(): void
    {
        config(['rekhu.key' => null]);
        $this->artisan('rekhu:core', ['--install' => null, '--key' => self::KEY, '--tenancy' => 'multi', '--locales' => 'en, nl ,ar'])
            ->expectsOutputToContain('Connected to Rekhu as acme / assistant')
            ->expectsOutputToContain('Rekhu::tenantUsing')
            ->assertSuccessful();
        $env = file_get_contents($this->env);
        $this->assertStringContainsString('REKHU_APP_KEY='.self::KEY, $env);
        $this->assertStringContainsString('REKHU_TENANCY=multi', $env);
        $this->assertStringContainsString('REKHU_LOCALES=en,nl,ar', $env);
    }

    public function test_install_refuses_a_wrong_key_and_private_without_an_address(): void
    {
        $this->artisan('rekhu:core', ['--install' => null, '--key' => 'sk-nope'])->expectsOutputToContain('not a Rekhu app key')->assertFailed();
        config(['rekhu.platform' => 'https://api.rekhu.ai']);
        $this->artisan('rekhu:core', ['--install' => null, '--key' => self::KEY, '--deployment' => 'private'])->expectsOutputToContain('needs --platform')->assertFailed();
    }

    public function test_a_knower_is_added_only_when_rekhu_has_it(): void
    {
        config(['rekhu.knowers' => []]);
        $this->artisan('rekhu:core', ['--install' => 'machines'])->expectsOutputToContain('Rekhu Machines is installed')->assertSuccessful();
        $this->assertStringContainsString('REKHU_KNOWERS=machines', file_get_contents($this->env));
        $this->artisan('rekhu:core', ['--install' => 'teleport'])->expectsOutputToContain('has no knower teleport')->assertFailed();
    }

    public function test_the_status_shows_the_connection(): void
    {
        $this->artisan('rekhu:core')->expectsOutputToContain('acme · assistant')->expectsOutputToContain('machines')->assertSuccessful();
    }

    public function test_usage_per_tenant_for_a_month(): void
    {
        $at = now()->startOfMonth()->addDay();
        DB::table('rekhu_usage')->insert([
            ['tenant_id' => 'shop-42', 'tenant_name' => 'Shop 42', 'user_id' => '7', 'knower' => 'machines', 'action' => 'check', 'model_calls' => 1, 'cost_usd' => 0.007, 'price' => 0.05, 'ms' => 10, 'created_at' => $at],
            ['tenant_id' => 'shop-42', 'tenant_name' => 'Shop 42', 'user_id' => '8', 'knower' => 'machines', 'action' => 'check', 'model_calls' => 0, 'cost_usd' => 0.001, 'price' => 0.05, 'ms' => 10, 'created_at' => $at],
            ['tenant_id' => 'shop-7', 'tenant_name' => 'Shop 7', 'user_id' => '9', 'knower' => 'machines', 'action' => 'choose', 'model_calls' => 0, 'cost_usd' => 0.001, 'price' => null, 'ms' => 10, 'created_at' => $at->copy()->subMonth()],
        ]);
        $csv = 'rekhu-usage-test.csv';
        $this->artisan('rekhu:core', ['--usage' => true, '--csv' => $csv])->expectsTable(
            ['tenant', 'name', 'knower', 'action', 'calls', 'model calls', 'Rekhu cost (USD)', 'your price'],
            [['shop-42', 'Shop 42', 'machines', 'check', 2, 1, '0.0080', '0.10']],
        )->assertSuccessful();
        $this->assertStringContainsString('shop-42,"Shop 42",machines,check,2,1,0.0080,0.10', file_get_contents(base_path($csv)));
        @unlink(base_path($csv));
    }

    public function test_check_reaches_rekhu_with_the_key(): void
    {
        Http::fake(['rekhu.test/v1/me' => Http::response(['customer' => ['name' => 'Acme'], 'agent' => 'laravel-dev', 'key' => ['label' => 'Shop'], 'project' => ['name' => 'Shop']], 200, ['x-rekhu-version' => '0.1.0'])]);
        $this->artisan('rekhu:core --check')->expectsOutputToContain('Rekhu answers with this key.')->assertSuccessful();
    }

    public function test_check_says_why_a_key_is_refused(): void
    {
        Http::fake(['rekhu.test/v1/me' => Http::response(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'This key is not valid on rekhu.test']], 401)]);
        $this->artisan('rekhu:core --check')->expectsOutputToContain('not valid on rekhu.test')->assertFailed();
    }
}
