<?php

declare(strict_types=1);

namespace Rekhu\Core\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Rekhu\Core\Exceptions\UnsafeFile;
use Rekhu\Core\Rekhu;
use Rekhu\Core\Tests\TestCase;

final class UploadInPartsTest extends TestCase
{
    private const CLEAN = ['verdict' => 'clean', 'virus' => ['status' => 'clean'], 'kind' => 'pdf', 'findings' => []];

    private const ID = 'f_aaaaaaaaaaaaaaaaaaaaaaaa';

    /** @var list<string> */
    private array $paths = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('rekhu.knowers', ['seshat']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->paths, 'is_file'));
        parent::tearDown();
    }

    public function test_parts_go_with_the_right_numbers_and_sizes_and_polling_ends_ready(): void
    {
        $this->platform(partSize: 10, scanning: 1);
        $path = $this->path('0123456789abcdefghijKLMNO');
        $seen = [];

        $file = Rekhu::seshat()->uploadInParts($path, 'big.pdf', function (int $sent, int $total, string $id) use (&$seen) {
            $seen[] = [$sent, $total, $id];
        }, everySeconds: 0.01);

        $this->assertSame(['file_id' => self::ID, 'name' => 'big.pdf', 'bytes' => 25, 'safety' => self::CLEAN], $file);
        $this->assertSame([[1, '0123456789'], [2, 'abcdefghij'], [3, 'KLMNO']], $this->parts());
        $this->assertSame([[0, 25, 'u_1'], [10, 25, 'u_1'], [20, 25, 'u_1'], [25, 25, 'u_1']], $seen);
        $this->assertSame(2, $this->sent('upload_status'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/upload_start') && $r['name'] === 'big.pdf' && $r['bytes'] === 25);
    }

    public function test_a_part_that_fails_once_is_sent_again(): void
    {
        $this->platform(partSize: 10, failOnce: 2);

        $this->assertSame(self::ID, Rekhu::seshat()->uploadInParts($this->path(str_repeat('x', 25)), everySeconds: 0.01)['file_id']);

        $this->assertSame([1, 2, 2, 3], array_column($this->parts(), 0));
    }

    public function test_resume_sends_only_the_missing_parts(): void
    {
        $this->platform(partSize: 10, missing: [2]);

        $file = Rekhu::seshat()->resumeUpload('u_1', $this->path('0123456789abcdefghijKLMNO'), everySeconds: 0.01);

        $this->assertSame(self::ID, $file['file_id']);
        $this->assertSame([[2, 'abcdefghij']], $this->parts());
        $this->assertSame(0, $this->sent('upload_start'));
    }

    public function test_an_unsafe_file_is_refused_with_its_report(): void
    {
        $safety = ['verdict' => 'refuse', 'virus' => ['status' => 'found', 'signature' => 'Eicar-Test-Signature'], 'kind' => 'pdf', 'findings' => [['code' => 'virus', 'decision' => 'refuse', 'about' => 'a virus was found']]];
        $this->platform(partSize: 10, refused: $safety);

        try {
            Rekhu::seshat()->uploadInParts($this->path(str_repeat('x', 12)), everySeconds: 0.01);
            $this->fail('an unsafe file is refused');
        } catch (UnsafeFile $e) {
            $this->assertSame('refused_unsafe', $e->type);
            $this->assertSame($safety, $e->safety);
            $this->assertStringContainsString('a virus was found', $e->getMessage());
        }
    }

    public function test_a_one_request_upload_refused_as_unsafe_is_its_own_exception(): void
    {
        Http::fake(['rekhu.test/v1/knowers/seshat/upload' => Http::response(['type' => 'error', 'error' => ['type' => 'refused_unsafe', 'message' => 'the file is not taken: a script that runs on opening']], 422)]);

        $this->expectException(UnsafeFile::class);
        Rekhu::seshat()->upload('%PDF-1.7', 'small.pdf');
    }

    public function test_a_file_over_20_mb_is_checked_by_its_file_id_after_parts(): void
    {
        $this->platform(partSize: 8 * 1024 * 1024);
        $total = 20 * 1024 * 1024 + 5;
        $path = $this->path(str_repeat('a', $total));

        Rekhu::seshat()->check($path, ['width_mm' => 148], explain: true);

        $this->assertSame([8 * 1024 * 1024, 8 * 1024 * 1024, 4 * 1024 * 1024 + 5], array_map(fn ($p) => strlen($p[1]), $this->parts()));
        $check = collect(Http::recorded())->map(fn ($pair) => $pair[0])->first(fn (Request $r) => str_ends_with($r->url(), '/check'));
        $this->assertFalse($check->hasHeader('x-file-name'));
        $this->assertSame(['file_id' => self::ID, 'order' => ['width_mm' => 148], 'explain' => true, 'language' => 'en'], json_decode($check->body(), true));
    }

    public function test_a_small_file_keeps_the_one_request_path(): void
    {
        $this->platform(partSize: 10);

        Rekhu::seshat()->check($this->path("%PDF-1.7\nsmall"), ['width_mm' => 148]);

        $this->assertSame(0, $this->sent('upload_start'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/check') && $r->body() === "%PDF-1.7\nsmall" && $r->hasHeader('x-file-name'));
    }

    /**
     * A platform for uploads in parts: upload_start with the part size, parts checked for their size, the status
     * scanning a few times, then ready (or refused).
     *
     * @param  list<int>  $missing
     */
    private function platform(int $partSize, int $scanning = 0, int $failOnce = 0, array $missing = [], ?array $refused = null): void
    {
        $state = ['bytes' => 25, 'parts' => 3, 'failed' => false, 'scanning' => $scanning];
        Http::fake(function (Request $r) use (&$state, $partSize, $failOnce, $missing, $refused) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/upload_start')) {
                $state['bytes'] = $r['bytes'];
                $state['parts'] = (int) ceil($r['bytes'] / $partSize);

                return Http::response(['upload_id' => 'u_1', 'part_size' => $partSize, 'parts' => $state['parts'], 'put' => '/v1/knowers/seshat/uploads/u_1/parts/{n}']);
            }
            if (preg_match('#/uploads/u_1/parts/(\d+)$#', $path, $m)) {
                $n = (int) $m[1];
                if ($n === $failOnce && ! $state['failed']) {
                    $state['failed'] = true;

                    return Http::response(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'try again']], 500);
                }
                $expected = $n < $state['parts'] ? $partSize : $state['bytes'] - $partSize * ($state['parts'] - 1);

                return strlen($r->body()) === $expected
                    ? Http::response(['parts' => $state['parts'], 'part_size' => $partSize, 'received' => $n, 'missing' => [], 'bytes_received' => 0])
                    : Http::response(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => "part {$n} is {$expected} bytes"]], 422);
            }
            if (str_ends_with($path, '/upload_complete')) {
                return Http::response(['upload_id' => 'u_1', 'status' => 'scanning']);
            }
            if (str_ends_with($path, '/upload_status')) {
                $open = $missing !== [] && ! collect(Http::recorded())->contains(fn ($pair) => str_contains($pair[0]->url(), '/parts/'));
                $status = match (true) {
                    $open => 'open',
                    $state['scanning']-- > 0 => 'scanning',
                    $refused !== null => 'refused',
                    default => 'ready',
                };

                return Http::response(['upload_id' => 'u_1', 'status' => $status, 'name' => 'big.pdf', 'bytes' => $state['bytes'], 'parts' => $state['parts'], 'part_size' => $partSize, 'missing' => $open ? $missing : [], 'bytes_received' => 15]
                    + ($status === 'ready' ? ['file_id' => self::ID, 'safety' => self::CLEAN] : []) + ($status === 'refused' ? ['safety' => $refused] : []));
            }

            return Http::response($this->answer(['file_id' => self::ID, 'status' => 'ok', 'findings' => []], 0, 0.01));
        });
    }

    /** The parts sent, in order, as [number, bytes]. */
    private function parts(): array
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $r) => str_contains($r->url(), '/parts/'))
            ->map(fn (Request $r) => [(int) substr($r->url(), strrpos($r->url(), '/') + 1), $r->body()])->values()->all();
    }

    /** How often an action was called. */
    private function sent(string $action): int
    {
        return collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/'.$action))->count();
    }

    /** A file on disk with these bytes. */
    private function path(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rekhu-');
        file_put_contents($path, $bytes);

        return $this->paths[] = $path;
    }
}
