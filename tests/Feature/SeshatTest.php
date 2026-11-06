<?php

declare(strict_types=1);

namespace Rekhu\Core\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            && json_decode($r->header('x-knower-input')[0], true) === ['order' => ['width_mm' => 148, 'height_mm' => 210], 'explain' => true, 'language' => 'en']);
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

    public function test_bytes_need_a_name(): void
    {
        $this->expectExceptionMessage("file's name");
        Rekhu::seshat()->check("%PDF-1.7\n".str_repeat('x', 5000));
    }
}
