<?php

declare(strict_types=1);

namespace Rekhu\Core\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Rekhu\Core\Events\KnowerUsed;
use Rekhu\Core\Exceptions\LimitReached;
use Rekhu\Core\Exceptions\RekhuException;
use Rekhu\Core\Rekhu;
use Rekhu\Core\Tests\TestCase;

final class KnowersTest extends TestCase
{
    protected function tearDown(): void
    {
        Rekhu::tenantUsing(null);
        Rekhu::limitsUsing(null);
        Rekhu::priceUsing(null);
        Rekhu::localeUsing(null);
        parent::tearDown();
    }

    public function test_a_call_names_the_tenant_and_user_and_is_kept_in_the_ledger(): void
    {
        config(['rekhu.tenancy' => 'multi']);
        Rekhu::tenantUsing(fn ($user) => ['id' => $user->team, 'name' => 'Shop 42']);
        Rekhu::priceUsing(fn ($tenant, $knower, $action) => $action === 'identify' ? 0.05 : null);
        Event::fake([KnowerUsed::class]);
        Http::fake(['rekhu.test/v1/knowers/machines/identify' => Http::response($this->answer(['match' => ['id' => 'exampla-offset-74']], 1, 0.0069))]);

        $answer = Rekhu::machines()->for($this->user())->identify('Exampla Offset 74-4');

        $this->assertSame('exampla-offset-74', $answer['match']['id']);
        Http::assertSent(fn (Request $r) => $r->hasHeader('x-api-key', self::KEY) && $r->hasHeader('x-agent', 'assistant') && $r->hasHeader('x-tenant', 'shop-42')
            && $r->hasHeader('x-tenant-name', 'Shop 42') && $r->hasHeader('x-user', '7') && $r['text'] === 'Exampla Offset 74-4');
        $row = DB::table('rekhu_usage')->first();
        $this->assertSame(['shop-42', '7', 'machines', 'identify', 1], [$row->tenant_id, $row->user_id, $row->knower, $row->action, (int) $row->model_calls]);
        $this->assertEqualsWithDelta(0.0069, (float) $row->cost_usd, 1e-9);
        $this->assertEqualsWithDelta(0.05, (float) $row->price, 1e-9);
        Event::assertDispatched(KnowerUsed::class, fn (KnowerUsed $e) => $e->tenant['id'] === 'shop-42' && $e->knower === 'machines' && $e->price === 0.05 && $e->modelCalls === 1);
    }

    public function test_single_tenancy_is_the_app_itself_and_a_queued_job_can_name_a_tenant(): void
    {
        Http::fake(['rekhu.test/*' => Http::response($this->answer(['choice' => null]))]);
        Rekhu::machines()->choose(['width_mm' => 85, 'height_mm' => 55, 'quantity' => 1000], [['ref' => 'm1', 'method' => 'offset']]);
        Http::assertSent(fn (Request $r) => $r->hasHeader('x-tenant', 'app') && $r->hasHeader('x-tenant-name', 'Print Portal') && ! $r->hasHeader('x-user'));

        Rekhu::machines()->forTenant('shop-9', 'Shop 9')->profile('exampla-offset-74', ['speed' => 12000]);
        Http::assertSent(fn (Request $r) => $r->hasHeader('x-tenant', 'shop-9') && $r['model'] === 'exampla-offset-74' && $r['known']['speed'] === 12000);
    }

    public function test_the_check_is_explained_in_the_users_language(): void
    {
        config(['rekhu.locales' => ['en', 'nl']]);
        Rekhu::localeUsing(fn () => 'nl-BE');
        Http::fake(['rekhu.test/*' => Http::response($this->answer(['ok' => true, 'findings' => []]))]);
        Rekhu::machines()->for($this->user())->check(['method' => 'offset', 'speed' => 18000], 'exampla-offset-74', true);
        Http::assertSent(fn (Request $r) => $r['language'] === 'nl' && $r['explain'] === true && $r['model'] === 'exampla-offset-74');
    }

    public function test_the_apps_own_limits_stop_a_call_before_it_is_sent(): void
    {
        config(['rekhu.tenancy' => 'multi']);
        Rekhu::tenantUsing(fn ($user) => $user->team);
        Rekhu::limitsUsing(fn ($tenant) => $tenant['id'] === 'shop-42' ? ['per_day_per_tenant' => 1] : []);
        Http::fake(['rekhu.test/*' => Http::response($this->answer())]);
        Rekhu::machines()->for($this->user())->identify('SM 52');

        try {
            Rekhu::machines()->for($this->user(8))->identify('Offset 74');
            $this->fail('the second call should be refused');
        } catch (LimitReached $e) {
            $this->assertSame('tenant', $e->scope);
            $this->assertSame(429, $e->status);
        }
        Http::assertSentCount(1);
        // another tenant still has its own day
        Rekhu::machines()->for($this->user(9, 'shop-7'))->identify('Offset 74');
        Http::assertSentCount(2);
    }

    public function test_refusals_and_missing_setup_are_said_plainly(): void
    {
        Http::fake(['rekhu.test/*' => Http::response(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'the job needs width_mm, height_mm and quantity']], 422)]);
        try {
            Rekhu::machines()->choose([], []);
            $this->fail('expected a refusal');
        } catch (RekhuException $e) {
            $this->assertSame(422, $e->status);
            $this->assertStringContainsString('width_mm', $e->getMessage());
        }
        $this->assertSame(0, DB::table('rekhu_usage')->count(), 'a refused call is not kept');

        config(['rekhu.knowers' => ['seshat']]);
        $this->expectExceptionMessage('REKHU_KNOWERS');
        Rekhu::machines()->identify('Offset 74');
    }

    public function test_multi_tenancy_without_a_tenant_is_refused(): void
    {
        config(['rekhu.tenancy' => 'multi']);
        Http::fake();
        $this->expectExceptionMessage('Rekhu::tenantUsing()');
        Rekhu::machines()->for($this->user())->identify('Offset 74');
    }

    public function test_private_mode_needs_its_own_address(): void
    {
        config(['rekhu.deployment' => 'private', 'rekhu.platform' => 'https://api.rekhu.ai']);
        Http::fake();
        $this->expectExceptionMessage('REKHU_PLATFORM');
        Rekhu::machines()->identify('Offset 74');
    }

    public function test_feedback_goes_to_the_knower(): void
    {
        Http::fake(['rekhu.test/*' => Http::response(['ref' => 'k_'.str_repeat('a', 20), 'outcome' => 'corrected', 'recorded' => true])]);
        Rekhu::machines()->feedback('k_'.str_repeat('a', 20), 'corrected', ['speed' => 12000]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/knowers/machines/feedback') && $r['correction']['speed'] === 12000);
    }
}
