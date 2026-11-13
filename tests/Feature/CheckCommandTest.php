<?php

declare(strict_types=1);

namespace Rekhu\Core\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Rekhu\Core\Tests\TestCase;

final class CheckCommandTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rekhu.knowers' => ['seshat']]);
        $this->file = tempnam(sys_get_temp_dir(), 'flyer').'.pdf';
        file_put_contents($this->file, '%PDF-1.7 a flyer');
        Http::fake([
            'rekhu.test/v1/knowers/seshat/check' => Http::response(['knower' => 'seshat', 'action' => 'check', 'file_id' => 'f_1', 'status' => 'warning',
                'findings' => [['code' => 'bleed_missing', 'level' => 'error', 'decision' => 'fix', 'pages' => [1]], ['code' => 'resolution_low', 'level' => 'warning', 'decision' => 'ask', 'pages' => [2]]],
                'meta' => ['model_calls' => 0, 'cost_usd' => 0.05]]),
            'rekhu.test/v1/knowers/seshat/codes' => Http::response(['codes' => ['bleed_missing' => ['about' => 'the page has no bleed'], 'resolution_low' => ['about' => 'a picture is coarse as placed']]]),
            'rekhu.test/v1/knowers/seshat/fix' => Http::response(['file_id' => 'f_2', 'applied' => [['fix' => 'add_bleed', 'changed' => true], ['side_effect_of' => 'add_bleed']], 'meta' => ['cost_usd' => 0.02]]),
            'rekhu.test/v1/knowers/seshat/files/f_2' => Http::response('%PDF-fixed', 200, ['Content-Type' => 'application/pdf']),
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @unlink($this->file.'.ready.pdf');
        parent::tearDown();
    }

    public function test_the_findings_are_listed_with_their_decision_and_text(): void
    {
        $this->artisan('rekhu:check', ['file' => $this->file, '--size' => '148x210'])
            ->expectsOutputToContain('148×210 mm, 3 mm bleed · warning')
            ->expectsOutputToContain('fix    the page has no bleed (page 1)')
            ->expectsOutputToContain('ask    a picture is coarse as placed (page 2)')
            ->assertSuccessful();
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/seshat/check') && json_decode($request->header('x-knower-input')[0], true)['order'] == ['width_mm' => 148, 'height_mm' => 210, 'bleed_mm' => 3]);
    }

    public function test_fix_saves_the_print_ready_version(): void
    {
        $out = $this->file.'.ready.pdf';
        $this->artisan('rekhu:check', ['file' => $this->file, '--size' => '148x210', '--fix' => true, '--out' => $out])
            ->expectsOutputToContain("saved to {$out}: add_bleed.")
            ->assertSuccessful();
        $this->assertSame('%PDF-fixed', file_get_contents($out));
    }

    public function test_it_refuses_without_a_file_a_size_or_a_place_for_the_fix(): void
    {
        $this->artisan('rekhu:check', ['file' => '/nowhere.pdf', '--size' => '148x210'])->expectsOutputToContain('No file at')->assertFailed();
        $this->artisan('rekhu:check', ['file' => $this->file])->expectsOutputToContain('--size=148x210')->assertFailed();
        $this->artisan('rekhu:check', ['file' => $this->file, '--size' => '148x210', '--fix' => true])->expectsOutputToContain('--out=')->assertFailed();
    }
}
