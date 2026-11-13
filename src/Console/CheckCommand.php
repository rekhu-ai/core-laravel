<?php

declare(strict_types=1);

namespace Rekhu\Core\Console;

use Illuminate\Console\Command;
use Rekhu\Core\Exceptions\RekhuException;
use Rekhu\Core\Rekhu;

/**
 * php artisan rekhu:check flyer.pdf --size=148x210 --bleed=3                  the findings, each with Seshat's decision
 * php artisan rekhu:check flyer.pdf --size=148x210 --fix --out=flyer-ready.pdf  and the print-ready version saved
 */
class CheckCommand extends Command
{
    protected $signature = 'rekhu:check
        {file : the print file (PDF, EPS, ZIP or a picture)}
        {--size= : the trimmed page in mm, width x height: 148x210}
        {--bleed=3 : the bleed in mm}
        {--pages= : the number of pages ordered}
        {--colour= : cmyk or any}
        {--max-ink= : the highest total ink in percent}
        {--min-ppi= : the lowest picture resolution}
        {--profile= : the print profile of the order}
        {--explain : add the explanation for the customer}
        {--json : the whole answer as JSON}
        {--fix : make the print-ready version with every automatic fix}
        {--out= : with --fix: where the print-ready version goes}';

    protected $description = 'Check a print file with Seshat, and make it print-ready';

    private const COLOURS = ['fix' => 'info', 'ask' => 'comment', 'refuse' => 'error', 'note' => 'line'];

    /** One check, its findings per line with Seshat's decision; with --fix the print-ready version saved. */
    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("No file at {$path}.");

            return self::FAILURE;
        }
        if (! preg_match('/^(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)$/', strtolower((string) $this->option('size')), $size)) {
            $this->error('Give the size of the trimmed page in mm: --size=148x210');

            return self::FAILURE;
        }
        if ($this->option('fix') && ! $this->option('out')) {
            $this->error('Say where the print-ready version goes: --fix --out=flyer-ready.pdf');

            return self::FAILURE;
        }
        $order = ['width_mm' => (float) $size[1], 'height_mm' => (float) $size[2], 'bleed_mm' => (float) $this->option('bleed')];
        foreach (['pages' => 'pages', 'max-ink' => 'max_ink', 'min-ppi' => 'min_ppi'] as $option => $key) {
            if ($this->option($option) !== null) {
                $order[$key] = (float) $this->option($option);
            }
        }
        foreach (['colour' => 'colour', 'profile' => 'profile'] as $option => $key) {
            if ($this->option($option) !== null) {
                $order[$key] = (string) $this->option($option);
            }
        }

        try {
            $seshat = Rekhu::seshat();
            $result = $seshat->check($path, $order, (bool) $this->option('explain'));
            $this->option('json') ? $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : $this->report($result, $order, basename($path), $seshat);
            if ($this->option('fix')) {
                $fixed = $seshat->fix((string) $result['file_id'], 'auto', $order);
                $seshat->downloadTo((string) $fixed['file_id'], (string) $this->option('out'));
                $changed = collect((array) ($fixed['applied'] ?? []))->filter(fn ($a) => ! empty($a['fix']) && ($a['changed'] ?? true) !== false)->pluck('fix')->all();
                $this->info("\nPrint-ready version saved to {$this->option('out')}: ".($changed ? implode(', ', $changed) : 'nothing needed changing').'.');
            }
        } catch (RekhuException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** The findings as lines a person reads, with the text of each code from Seshat's code list. */
    private function report(array $result, array $order, string $name, $seshat): void
    {
        try {
            $about = (array) ($seshat->codes()['codes'] ?? []);
        } catch (RekhuException) {
            $about = [];
        }
        $findings = $result['findings'] ?? [];
        if (! $findings) {
            foreach ((array) ($result['files'] ?? []) as $file) {
                foreach ((array) ($file['findings'] ?? []) as $f) {
                    $findings[] = $f + ['file' => $file['name'] ?? null];
                }
            }
        }
        $this->line("{$name} · {$order['width_mm']}×{$order['height_mm']} mm, {$order['bleed_mm']} mm bleed · ".($result['status'] ?? ''));
        if (! $findings) {
            $this->line('  Nothing to change: the file is ready to print.');
        }
        foreach ($findings as $f) {
            $pages = (array) ($f['pages'] ?? []);
            $where = implode(', ', array_filter([$f['file'] ?? null, $pages ? (count($pages) > 1 ? 'pages ' : 'page ').implode(', ', array_slice($pages, 0, 8)).(count($pages) > 8 ? '…' : '') : null]));
            $text = '  '.str_pad((string) $f['decision'], 6).' '.($about[$f['code']]['about'] ?? $f['code']).($where !== '' ? " ({$where})" : '');
            $this->{self::COLOURS[$f['decision']] ?? 'line'}($text);
        }
        if (! empty($result['explanation'])) {
            $this->line("\n".(is_string($result['explanation']) ? $result['explanation'] : ($result['explanation']['text'] ?? json_encode($result['explanation']))));
        }
        $this->line(sprintf("\nfile_id %s · cost $%.3f", $result['file_id'] ?? '', (float) ($result['meta']['cost_usd'] ?? 0)));
    }
}
