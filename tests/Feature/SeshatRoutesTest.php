<?php

declare(strict_types=1);

namespace Rekhu\Core\Tests\Feature;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Rekhu\Core\Http\SeshatController;
use Rekhu\Core\Rekhu;
use Rekhu\Core\Tests\TestCase;

final class SeshatRoutesTest extends TestCase
{
    private const FILE = 'f_aaaaaaaaaaaaaaaaaaaaaaaa';

    private const FIXED = 'f_bbbbbbbbbbbbbbbbbbbbbbbb';

    private const P = ['X-Rekhu-Protocol' => '1'];

    protected function defineRoutes($router): void
    {
        Rekhu::seshatRoutes(['middleware' => [], 'specs' => ['bleed_mm' => 3, 'min_ppi' => null]]);
        $router->prefix('strict')->group(fn () => Rekhu::seshatRoutes(['middleware' => [], 'fixes' => ['add_bleed'], 'widget' => false, 'max_mb' => 0]));
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['rekhu.knowers' => ['seshat']]);
        cache()->flush();
        $this->be($this->user());
        Http::fake([
            'rekhu.test/v1/knowers/seshat/check' => Http::response(['file_id' => self::FILE, 'status' => 'warning', 'file' => ['pages' => 2],
                'findings' => [['code' => 'bleed_missing', 'decision' => 'fix', 'fix' => 'add_bleed', 'pages' => [1]], ['code' => 'resolution_low', 'decision' => 'ask', 'pages' => [2]]],
                'explanation' => ['summary' => 'One thing to fix.'], 'meta' => ['cost_usd' => 0.05]]),
            'rekhu.test/v1/knowers/seshat/codes' => Http::response(['codes' => ['bleed_missing' => ['about' => 'the page has no bleed'], 'resolution_low' => ['about' => 'a picture is coarse']], 'fixes' => ['add_bleed' => ['about' => 'adds bleed by mirroring the page edges']], 'texts' => ['en' => ['bleed_missing' => 'The page has no bleed'], 'nl' => ['bleed_missing' => 'De pagina heeft geen afloop', 'resolution_low' => 'Afbeeldingen zijn wat klein']]]),
            'rekhu.test/v1/knowers/seshat/preview' => Http::response(['file_id' => self::FILE, 'image' => 'data:image/png;base64,'.base64_encode('PNGDATA')]),
            'rekhu.test/v1/knowers/seshat/fix' => Http::response(['file_id' => self::FIXED, 'from_file_id' => self::FILE, 'name' => 'flyer-print.pdf',
                'applied' => [['fix' => 'add_bleed', 'changed' => true], ['side_effect' => 'x resolved', 'resolved' => 'x']],
                'check' => ['status' => 'warning', 'file' => ['pages' => 2], 'findings' => [['code' => 'resolution_low', 'decision' => 'ask', 'pages' => [2]]]]]),
            'rekhu.test/v1/knowers/seshat/files/*' => Http::response('%PDF-fixed', 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"flyer-print.pdf\"; filename*=UTF-8''flyer-print.pdf"]),
        ]);
    }

    private function upload(array $specs = ['width_mm' => 148, 'height_mm' => 210], string $prefix = ''): TestResponse
    {
        return $this->withHeaders(self::P)->post($prefix.'/rekhu/seshat/check', [
            'file' => UploadedFile::fake()->createWithContent('flyer.pdf', '%PDF-1.7 a flyer'), 'specs' => json_encode($specs), 'locale' => 'nl-NL',
        ]);
    }

    public function test_check_answers_the_findings_with_their_sentence_against_the_specs_and_the_app_defaults(): void
    {
        $this->upload(['width_mm' => 148, 'height_mm' => '210', 'min_ppi' => 300, 'evil' => 'x'])
            ->assertOk()->assertJsonPath('protocol', 1)->assertJsonPath('file_id', self::FILE)->assertJsonPath('pages', 2)
            ->assertJsonPath('findings.0', ['code' => 'bleed_missing', 'decision' => 'fix', 'about' => 'De pagina heeft geen afloop', 'fix' => 'add_bleed', 'pages' => [1]])
            ->assertJsonPath('explanation.summary', 'One thing to fix.')->assertJsonMissingPath('meta');
        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/seshat/check')) {
                return false;
            }
            $input = json_decode($request->header('x-knower-input')[0], true);

            return $input['order'] == ['bleed_mm' => 3, 'width_mm' => 148, 'height_mm' => 210, 'min_ppi' => 300] && $input['language'] === 'nl' && $input['explain'] === true && $input['name'] === 'flyer.pdf';
        });
    }

    public function test_fix_makes_all_or_the_chosen_allowed_fixes_and_answers_the_check_of_the_fixed_file(): void
    {
        $this->withHeaders(self::P)->postJson('/rekhu/seshat/fix', ['file_id' => self::FILE, 'specs' => ['width_mm' => 148, 'height_mm' => 210], 'locale' => 'nl'])
            ->assertOk()->assertJsonPath('file_id', self::FIXED)->assertJsonPath('fixed_from', self::FILE)
            ->assertJsonPath('applied', [['fix' => 'add_bleed', 'about' => 'adds bleed by mirroring the page edges']])
            ->assertJsonPath('check.findings', [['code' => 'resolution_low', 'decision' => 'ask', 'about' => 'Afbeeldingen zijn wat klein', 'pages' => [2]]]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/seshat/fix') && $r->data()['fixes'] === 'auto');

        $this->withHeaders(self::P)->postJson('/strict/rekhu/seshat/fix', ['file_id' => self::FILE])->assertStatus(422);
        $this->withHeaders(self::P)->postJson('/strict/rekhu/seshat/fix', ['file_id' => self::FILE, 'fixes' => ['convert_cmyk']])->assertOk()->assertJsonPath('applied', [])->assertJsonPath('check', null);
        $this->withHeaders(self::P)->postJson('/strict/rekhu/seshat/fix', ['file_id' => self::FILE, 'fixes' => ['add_bleed', 'convert_cmyk', 'DROP TABLE']])->assertOk();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/seshat/fix') && $r->data()['fixes'] === [['fix' => 'add_bleed']]);
    }

    public function test_preview_is_a_png_the_file_downloads_with_its_name_and_the_widget_is_served(): void
    {
        $preview = $this->get('/rekhu/seshat/preview?file='.self::FILE.'&page=2');
        $preview->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame('PNGDATA', $preview->getContent());
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/seshat/preview') && $r->data()['page'] === 2);
        $download = $this->get('/rekhu/seshat/files/'.self::FIXED);
        $download->assertOk();
        $this->assertStringContainsString('flyer-print.pdf', (string) $download->headers->get('content-disposition'));
        $this->assertSame('%PDF-fixed', file_get_contents($download->baseResponse->getFile()->getPathname()));
        $this->get('/rekhu/seshat/widget.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
    }

    public function test_refused_without_user_protocol_size_or_known_file_and_the_account_problem_is_kept_from_the_user(): void
    {
        $this->post('/rekhu/seshat/check')->assertStatus(403);
        $this->withHeaders(['X-Rekhu-Protocol' => '2'])->get('/rekhu/seshat/preview?file='.self::FILE)->assertStatus(400);
        $this->flushHeaders();
        $this->get('/rekhu/seshat/preview?file=nope')->assertNotFound();
        $this->upload(['width_mm' => 148])->assertStatus(400);
        $this->upload(prefix: '/strict')->assertStatus(413);
        Http::swap(new Factory);
        Http::fake(['rekhu.test/*' => Http::response(['type' => 'error', 'error' => ['type' => 'credit_exhausted', 'message' => 'the credit of acme is used up']], 402)]);
        $response = $this->upload();
        $response->assertStatus(503);
        $this->assertStringNotContainsString('acme', (string) $response->getContent());
        auth()->forgetGuards();
        $this->upload()->assertStatus(401);
    }

    public function test_a_finding_reads_in_the_language_sent_else_the_browsers_else_english(): void
    {
        $about = fn (?string $locale, ?string $header) => collect($this->withHeaders(self::P + ($header ? ['Accept-Language' => $header] : []))->post('/rekhu/seshat/check', array_filter([
            'file' => UploadedFile::fake()->createWithContent('a.pdf', '%PDF'), 'specs' => json_encode(['width_mm' => 148, 'height_mm' => 210]), 'locale' => $locale,
        ]))->json('findings'))->pluck('about')->all();
        $this->assertSame(['The page has no bleed', 'a picture is coarse'], $about('de-DE', null));
        $this->flushHeaders();
        $this->assertSame(['De pagina heeft geen afloop', 'Afbeeldingen zijn wat klein'], $about(null, 'nl-NL,nl;q=0.9'));
    }

    public function test_specs_from_a_browser_keep_only_the_fields_of_the_specs(): void
    {
        $this->assertSame(['width_mm' => 148, 'height_mm' => 210, 'colour' => 'cmyk', 'spot_colours_allowed' => true],
            SeshatController::cleanSpecs(['width_mm' => '148', 'height_mm' => 210, 'bleed_mm' => 'x', 'colour' => 'cmyk', 'api_key' => 'no', 'spot_colours_allowed' => true]));
    }
}
