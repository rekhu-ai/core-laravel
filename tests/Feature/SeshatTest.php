<?php

declare(strict_types=1);

namespace Rekhu\Core\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Rekhu\Core\Exceptions\FileNotFound;
use Rekhu\Core\Exceptions\InvalidRequest;
use Rekhu\Core\Exceptions\ModuleNotEnabled;
use Rekhu\Core\Exceptions\RekhuException;
use Rekhu\Core\Exceptions\Unavailable;
use Rekhu\Core\Rekhu;
use Rekhu\Core\Tests\TestCase;

final class SeshatTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('rekhu.knowers', ['machines', 'seshat']);
    }

    public function test_a_print_file_goes_as_the_body_with_its_order(): void
    {
        Http::fake(['rekhu.test/v1/knowers/seshat/check' => Http::response($this->answer(['file_id' => 'f_1', 'status' => 'warning', 'findings' => [['code' => 'bleed_missing', 'decision' => 'fix']]], 1, 0.01))]);
        $upload = UploadedFile::fake()->createWithContent('flyer.pdf', "%PDF-1.7\nflyer");

        $check = Rekhu::seshat()->for($this->user())->check($upload, ['width_mm' => 148, 'height_mm' => 210], explain: true);

        $this->assertSame('f_1', $check['file_id']);
        Http::assertSent(fn (Request $r) => $r->body() === "%PDF-1.7\nflyer" && $r->hasHeader('x-file-name', 'flyer.pdf')
            && json_decode($r->header('x-knower-input')[0], true) === ['order' => ['width_mm' => 148, 'height_mm' => 210], 'explain' => true, 'language' => 'en', 'name' => 'flyer.pdf']);
        $this->assertSame(['seshat', 'check'], [DB::table('rekhu_usage')->value('knower'), DB::table('rekhu_usage')->value('action')]);
    }

    public function test_fix_preview_and_download(): void
    {
        Http::fake([
            'rekhu.test/v1/knowers/seshat/fix' => Http::response($this->answer(['file_id' => 'f_2', 'applied' => [['fix' => 'add_bleed']]])),
            'rekhu.test/v1/knowers/seshat/preview' => Http::response($this->answer(['image' => 'data:image/png;base64,iVBOR'])),
            'rekhu.test/v1/knowers/seshat/files/f_2' => Http::response('%PDF-fixed', 200, ['Content-Type' => 'application/pdf']),
        ]);
        $this->assertSame('f_2', Rekhu::seshat()->fix('f_1', 'auto', ['width_mm' => 148, 'height_mm' => 210])['file_id']);
        $this->assertStringStartsWith('data:image/png', Rekhu::seshat()->preview('f_2')['image']);
        $this->assertSame('%PDF-fixed', Rekhu::seshat()->download('f_2'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/fix') && $r['fixes'] === 'auto' && $r['file_id'] === 'f_1');
    }

    public function test_a_template_is_uploaded_once_and_its_rows_go_in_steps(): void
    {
        $id = 'f_'.str_repeat('c', 24);
        Http::fake([
            'rekhu.test/v1/knowers/seshat/upload' => Http::response(['file_id' => $id]),
            'rekhu.test/v1/knowers/seshat/fill_template' => Http::response(['status' => 'open', 'job_id' => 'j_9']),
            'rekhu.test/v1/knowers/seshat/poll' => Http::response(['job_id' => 'j_9', 'status' => 'done', 'result' => ['files' => [['rows' => '1-250']]]]),
            'rekhu.test/v1/knowers/seshat/text_positions' => Http::response(['found' => [['find' => '[name]', 'page' => 1, 'font' => 'Helvetica', 'size' => 14]]]),
        ]);
        $rows = array_map(fn ($i) => ['[name]' => "Student {$i}"], range(1, 2500));
        Rekhu::seshat()->fillTemplate("%PDF-1.7\ntemplate", $rows, ['name' => 'certificate.pdf', 'name_by' => '[name]', 'align' => 'center', 'webhook_url' => 'https://shop.test/hook']);
        $fills = collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $r) => str_ends_with($r->url(), '/fill_template'))->map(fn (Request $r) => json_decode($r->body(), true))->values();
        $this->assertSame([[1000, $id, true, null], [1000, 'j_9', true, null], [500, 'j_9', false, 'https://shop.test/hook']], $fills->map(fn ($f) => [count($f['rows']), $f['file_id'] ?? $f['job_id'], (bool) ($f['more'] ?? false), $f['webhook_url'] ?? null])->all());
        $this->assertSame('[name]', $fills[0]['name_by']);
        $this->assertSame('center', $fills[0]['align']);
        $this->assertArrayNotHasKey('align', $fills[1]);
        Http::assertSentCount(4);
        $this->assertSame('done', Rekhu::seshat()->waitFor('j_9', 5, 0.01)['status']);
        $this->assertSame('Helvetica', Rekhu::seshat()->textPositions($id, ['[name]'])['found'][0]['font']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/text_positions') && json_decode($r->body(), true) === ['file_id' => $id, 'find' => ['[name]']]);
    }

    public function test_bytes_need_a_name(): void
    {
        $this->expectExceptionMessage("file's name");
        Rekhu::seshat()->check("%PDF-1.7\n".str_repeat('x', 5000));
    }

    public function test_ai_only_enhance_preview_poll_and_modules(): void
    {
        Http::fake([
            'rekhu.test/v1/knowers/seshat/modules' => Http::response(['modules' => ['preflight' => false, 'ai_enhance' => true], 'modes' => ['upscale']]),
            'rekhu.test/v1/knowers/seshat/enhance' => Http::response($this->answer(['file_id' => 'f_'.str_repeat('b', 24), 'format' => 'png', 'width_px' => 1182, 'confidence' => 0.8, 'warnings' => []] + ['meta' => ['model_calls' => 0, 'cost_usd' => 0.04, 'usage' => [['module' => 'ai_enhance', 'item' => 'seshat_upscale', 'units' => 1, 'cost_usd' => 0.04]]]])),
            'rekhu.test/v1/knowers/seshat/enhance_preview' => Http::response(['image' => 'data:image/png;base64,iVBOR']),
            'rekhu.test/v1/knowers/seshat/poll' => Http::response(['job_id' => 'j_1', 'status' => 'done', 'result' => ['file_id' => 'f_x']]),
        ]);
        $this->assertTrue(Rekhu::seshat()->modules()['modules']['ai_enhance']);
        $photo = UploadedFile::fake()->createWithContent('photo.png', "\x89PNG fake");
        $result = Rekhu::seshat()->for($this->user())->enhance($photo, ['denoise', 'upscale'], ['ppi' => 300, 'width_mm' => 100, 'height_mm' => 70], ['webhook_url' => 'https://shop.test/hooks/rekhu']);
        $this->assertSame(1182, $result['width_px']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/enhance') && $r->body() === "\x89PNG fake"
            && json_decode($r->header('x-knower-input')[0], true) === ['mode' => ['denoise', 'upscale'], 'options' => ['ppi' => 300, 'width_mm' => 100, 'height_mm' => 70], 'webhook_url' => 'https://shop.test/hooks/rekhu', 'name' => 'photo.png']);
        // a kept file goes by its id, as JSON
        Rekhu::seshat()->enhance('f_'.str_repeat('a', 24), 'remove_background');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/enhance') && (json_decode($r->body(), true)['file_id'] ?? null) === 'f_'.str_repeat('a', 24) && (json_decode($r->body(), true)['mode'] ?? null) === ['remove_background']);
        $this->assertStringStartsWith('data:image/png', Rekhu::seshat()->enhancePreview($result['file_id'])['image']);
        $this->assertSame('done', Rekhu::seshat()->poll('j_1')['status']);
        // the AI call is in the ledger with its cost; previews and polls are not charged and not kept
        $this->assertSame(['enhance', 'enhance'], DB::table('rekhu_usage')->pluck('action')->all());
        $this->assertEqualsWithDelta(0.04, (float) DB::table('rekhu_usage')->value('cost_usd'), 1e-9);
    }

    public function test_a_webhook_is_verified_by_its_signature_and_age(): void
    {
        $body = '{"job_id":"j_1","status":"done"}';
        $t = (string) time();
        $header = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$body, 'whsec_abc');
        $this->assertTrue(Rekhu::verifyWebhook($body, $header, 'whsec_abc'));
        $this->assertFalse(Rekhu::verifyWebhook($body.' ', $header, 'whsec_abc'), 'a changed body');
        $this->assertFalse(Rekhu::verifyWebhook($body, $header, 'whsec_other'), 'another secret');
        $old = (string) (time() - 3600);
        $this->assertFalse(Rekhu::verifyWebhook($body, 't='.$old.',v1='.hash_hmac('sha256', $old.'.'.$body, 'whsec_abc'), 'whsec_abc'), 'too old');
    }

    public function test_a_file_name_in_any_language_arrives_whole(): void
    {
        Http::fake(['rekhu.test/v1/knowers/seshat/upload' => Http::response(['file_id' => 'f_'.str_repeat('c', 24), 'name' => 'Visitekaartje-Müller-مرحبا.pdf'])]);
        Rekhu::seshat()->upload(UploadedFile::fake()->createWithContent('Visitekaartje-Müller-مرحبا.pdf', '%PDF-1.7'));
        Http::assertSent(fn (Request $r) => $r->hasHeader('x-file-name', rawurlencode('Visitekaartje-Müller-مرحبا.pdf'))
            && json_decode($r->header('x-knower-input')[0], true)['name'] === 'Visitekaartje-Müller-مرحبا.pdf');
    }

    public function test_every_refusal_is_its_own_exception(): void
    {
        Http::fake([
            'rekhu.test/v1/knowers/seshat/fix' => Http::response(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'unknown fix "make_coffee"']], 422),
            'rekhu.test/v1/knowers/seshat/enhance' => Http::response(['type' => 'error', 'error' => ['type' => 'module_not_enabled', 'message' => 'off']], 403),
            'rekhu.test/v1/knowers/seshat/files/*' => Http::response(['type' => 'error', 'error' => ['type' => 'not_found_error', 'message' => 'no such file']], 404),
        ]);
        foreach ([
            [fn () => Rekhu::seshat()->for($this->user())->fix('f_1', [['fix' => 'make_coffee']]), InvalidRequest::class],
            [fn () => Rekhu::seshat()->for($this->user())->enhance('f_'.str_repeat('a', 24), 'upscale'), ModuleNotEnabled::class],
            [fn () => Rekhu::seshat()->download('f_gone'), FileNotFound::class],
        ] as [$call, $class]) {
            try {
                $call();
                $this->fail("expected {$class}");
            } catch (RekhuException $e) {
                $this->assertInstanceOf($class, $e);
            }
        }
        $this->assertSame(0, DB::table('rekhu_usage')->count(), 'a refused call is not kept');
    }

    public function test_a_briefly_unreachable_platform_is_tried_again_under_one_idempotency_key(): void
    {
        Sleep::fake();
        Http::fakeSequence('rekhu.test/v1/knowers/seshat/fix')
            ->push('<html>Service Unavailable</html>', 503)
            ->push('', 503)
            ->push($this->answer(['file_id' => 'f_3', 'applied' => [['fix' => 'add_bleed']]]));
        $this->assertSame('f_3', Rekhu::seshat()->for($this->user())->fix('f_1', [['fix' => 'add_bleed']])['file_id']);
        $keys = collect(Http::recorded())->map(fn ($pair) => $pair[0]->header('idempotency-key')[0] ?? null)->all();
        $this->assertCount(3, $keys);
        $this->assertCount(1, array_unique($keys), 'every try carries the same key, so it is never charged twice');
        Sleep::assertSequence([Sleep::for(2)->seconds(), Sleep::for(4)->seconds()]);
    }

    public function test_an_unreachable_platform_is_unavailable_after_four_tries(): void
    {
        Sleep::fake();
        Http::fake(['rekhu.test/v1/knowers/seshat/codes' => Http::response('', 502)]);
        try {
            Rekhu::seshat()->for($this->user())->codes();
            $this->fail('an unreachable platform is not an answer');
        } catch (Unavailable $e) {
            $this->assertSame(502, $e->status);
        }
        Http::assertSentCount(4);
        Sleep::assertSequence([Sleep::for(2)->seconds(), Sleep::for(4)->seconds(), Sleep::for(8)->seconds()]);
    }

    public function test_a_503_with_the_platforms_own_error_fails_at_once(): void
    {
        Sleep::fake();
        Http::fake(['rekhu.test/v1/knowers/seshat/fix' => Http::response(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'the fix could not run']], 503)]);
        try {
            Rekhu::seshat()->for($this->user())->fix('f_1', [['fix' => 'add_bleed']]);
            $this->fail('a refusal from the platform is not tried again');
        } catch (Unavailable $e) {
            $this->assertSame('the fix could not run', $e->getMessage());
        }
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_a_busy_rekhu_is_tried_again_under_one_idempotency_key(): void
    {
        Sleep::fake();
        Http::fakeSequence('rekhu.test/v1/knowers/seshat/fix')
            ->push(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'busy']], 503, ['retry-after' => '1'])
            ->push($this->answer(['file_id' => 'f_3', 'applied' => [['fix' => 'add_bleed']]]))
            ->push('', 529, ['retry-after' => '1'])
            ->push($this->answer(['file_id' => 'f_4', 'applied' => [['fix' => 'add_bleed']]]));
        $this->assertSame('f_3', Rekhu::seshat()->for($this->user())->fix('f_1', [['fix' => 'add_bleed']])['file_id']);
        $this->assertSame('f_4', Rekhu::seshat()->for($this->user())->fix('f_1', [['fix' => 'add_bleed']])['file_id']);
        $keys = collect(Http::recorded())->map(fn ($pair) => $pair[0]->header('idempotency-key')[0] ?? null)->all();
        $this->assertCount(4, $keys);
        $this->assertSame($keys[0], $keys[1], 'the retry of the overloaded 503 carries the same key');
        $this->assertSame($keys[2], $keys[3], 'the retry of the 529 carries the same key');
        Sleep::assertSequence([Sleep::for(1)->seconds(), Sleep::for(1)->seconds()]);
    }

    public function test_a_kept_file_can_be_removed_and_the_codes_listed(): void
    {
        Http::fake([
            'rekhu.test/v1/knowers/seshat/files/f_1' => Http::response('', 204),
            'rekhu.test/v1/knowers/seshat/codes' => Http::response(['version' => '1.0', 'codes' => ['bleed_missing' => ['decisions' => ['fix']]]]),
            'rekhu.test/v1/knowers/seshat/fonts/Montserrat-Bold' => Http::response("\x00\x01\x00\x00ttf", 200, ['x-font-licence' => 'OFL-1.1']),
            'rekhu.test/v1/knowers/seshat/fonts' => Http::response(['count' => 1, 'families' => [['family' => 'Montserrat', 'styles' => [['name' => 'Montserrat-Bold']]]]]),
        ]);
        Rekhu::seshat()->remove('f_1');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/files/f_1'));
        $this->assertSame('1.0', Rekhu::seshat()->codes()['version']);
        $this->assertSame('Montserrat-Bold', Rekhu::seshat()->fonts()['families'][0]['styles'][0]['name']);
        $font = Rekhu::seshat()->fontFile('Montserrat-Bold');
        $this->assertSame(['name' => 'Montserrat-Bold', 'licence' => 'OFL-1.1', 'bytes' => "\x00\x01\x00\x00ttf"], $font);
        $this->assertSame(0, DB::table('rekhu_usage')->count(), 'listing the codes and fonts is not charged');
    }

    public function test_files_are_merged_split_and_drawn_as_pictures(): void
    {
        Http::fake([
            'rekhu.test/v1/knowers/seshat/merge' => Http::response(['file_id' => 'f_m', 'pages' => 3, 'meta' => ['cost_usd' => 0.01]]),
            'rekhu.test/v1/knowers/seshat/split' => Http::response(['parts' => [['range' => '1-2', 'file_id' => 'f_a']], 'meta' => ['cost_usd' => 0.01]]),
            'rekhu.test/v1/knowers/seshat/render' => Http::response(['pictures' => [['page' => 1, 'file_id' => 'f_p']], 'meta' => ['cost_usd' => 0.01]]),
        ]);
        $this->assertSame(3, Rekhu::seshat()->merge(['f_1', 'f_2'])['pages']);
        $this->assertSame('f_a', Rekhu::seshat()->split('f_m', ['1-2'])['parts'][0]['file_id']);
        $this->assertSame('f_p', Rekhu::seshat()->render('f_m', 'jpg', 72, '1')['pictures'][0]['file_id']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/merge') && $r['file_ids'] === ['f_1', 'f_2']);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/render') && $r['format'] === 'jpg' && $r['dpi'] === 72);
        $this->assertSame(3, DB::table('rekhu_usage')->count(), 'merge, split and render are charged and kept in the ledger');
    }

    public function test_recipes_plates_versions_compared_and_the_customer_report(): void
    {
        Http::fake(['rekhu.test/v1/knowers/seshat/*' => Http::response($this->answer(['ok' => true]))]);
        $s = Rekhu::seshat();
        $s->saveRecipe('A5 flyer', ['preset' => 'sheetfed_offset', 'width_mm' => 148, 'height_mm' => 210], 'auto');
        $s->fix('f_1', recipe: 'A5 flyer');
        $s->fix('f_1');
        $s->plates('f_1', 2, profile: 'fogra51', maxInk: 300);
        $s->compareFiles('f_2', 'f_1');
        $s->report('f_1', recipe: 'A5 flyer', language: 'nl', texts: ['rgb_colours' => 'Uw bestand bevat RGB.']);
        $s->recipes();
        $s->deleteRecipe('A5 flyer');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/recipe_save') && $r['order']['preset'] === 'sheetfed_offset' && $r['fixes'] === 'auto');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/fix') && ($r->data()['recipe'] ?? null) === 'A5 flyer' && ! array_key_exists('fixes', $r->data()));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/fix') && ! array_key_exists('recipe', $r->data()) && ($r->data()['fixes'] ?? null) === 'auto');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/plates') && $r['page'] === 2 && $r['profile'] === 'fogra51' && (float) $r['max_ink'] === 300.0);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/compare_files') && $r['against_file_id'] === 'f_1');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/report') && $r['language'] === 'nl' && $r['texts']['rgb_colours'] === 'Uw bestand bevat RGB.');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/recipe_delete') && $r['name'] === 'A5 flyer');
        $this->assertSame(5, DB::table('rekhu_usage')->count(), 'the two fixes, plates, compare and report are charged; recipes are not');
    }
}
