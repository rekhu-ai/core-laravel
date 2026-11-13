<?php

declare(strict_types=1);

namespace Rekhu\Core\Http;

use Illuminate\Support\Facades\Route;

/**
 * The routes of the file check on your own site, protocol 1 of @rekhu/seshat-widget (see its PROTOCOL.md):
 *
 *     POST {path}/check            the print file and its specs
 *     POST {path}/fix              the fixes, and the fixed file checked again
 *     GET  {path}/preview          a page as a PNG with trim, bleed and safe zone
 *     GET  {path}/files/{file}     the file, to download
 *     GET  {path}/widget.js        the widget itself, for a Blade page without npm
 */
class SeshatRoutes
{
    /** @param array{path?: string, middleware?: list<string>, max_mb?: int, explain?: bool, specs?: array, fixes?: ?list<string>, recipes?: bool, widget?: bool} $options */
    public static function register(array $options = []): void
    {
        $config = array_replace((array) config('rekhu.seshat', []), $options);
        $path = trim((string) ($config['path'] ?? 'rekhu/seshat'), '/');

        if ($options['widget'] ?? true) {
            Route::get($path.'/widget.js', fn () => response(file_get_contents(__DIR__.'/../../resources/js/seshat-widget.js'), 200, [
                'Content-Type' => 'application/javascript; charset=utf-8',
                'Cache-Control' => app()->environment('local') ? 'no-cache' : 'public, max-age=86400',
            ]))->name('rekhu.seshat.widget');
        }

        Route::middleware([...(array) ($config['middleware'] ?? ['web', 'auth']), EnsureSeshatProtocol::class])
            ->prefix($path)
            ->name('rekhu.seshat.')
            ->group(function () use ($config) {
                Route::post('check', [SeshatController::class, 'check'])->defaults('rekhu_seshat', $config)->name('check');
                Route::post('fix', [SeshatController::class, 'fix'])->defaults('rekhu_seshat', $config)->name('fix');
                Route::get('preview', [SeshatController::class, 'preview'])->defaults('rekhu_seshat', $config)->name('preview');
                Route::get('files/{file}', [SeshatController::class, 'download'])->where('file', 'f_[a-z0-9]{24}')->defaults('rekhu_seshat', $config)->name('file');
            });
    }
}
