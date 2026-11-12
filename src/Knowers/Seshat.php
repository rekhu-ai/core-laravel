<?php

declare(strict_types=1);

namespace Rekhu\Core\Knowers;

use Illuminate\Http\UploadedFile;
use Rekhu\Core\Client;
use Rekhu\Core\Exceptions\RekhuException;
use Rekhu\Core\Exceptions\UnsafeFile;

/**
 * Seshat: knows print files. It checks a file against the order it is for, fixes what is safe, asks what the customer
 * must choose, refuses what cannot be printed, and explains it in the user's language.
 *
 *     $check = Rekhu::seshat()->for($user)->check($request->file('artwork'), ['width_mm' => 148, 'height_mm' => 210, 'bleed_mm' => 3, 'pages' => 2], explain: true);
 *     $fixed = Rekhu::seshat()->fix($check['file_id'], 'auto', $order);
 *     Storage::put('print/'.$fixed['name'], Rekhu::seshat()->download($fixed['file_id']));
 *
 * The order: width_mm, height_mm (the finished size), bleed_mm (3), pages, colour (cmyk), min_ppi (250), safe_mm (3),
 * max_ink (320), spot_colours_allowed. Files are kept by Rekhu for a day, so a fix or a preview can follow a check.
 *
 * With the ai_enhance module (switched on per application by Rekhu), one picture is improved without a check:
 *
 *     $result = Rekhu::seshat()->enhance($request->file('photo'), ['denoise', 'upscale'], ['ppi' => 300, 'width_mm' => 100, 'height_mm' => 70]);
 *     $result['status'] === 'queued' ? $job = $result['job_id'] : Storage::put('print/photo.png', Rekhu::seshat()->download($result['file_id']));
 *
 * A file larger than 20 MB goes up in parts by itself (see uploadInParts); every file is checked for safety.
 */
class Seshat extends Knower
{
    public const IN_PARTS_FROM = 20 * 1024 * 1024;

    public function __construct(Client $client)
    {
        parent::__construct($client, 'seshat');
    }

    /**
     * A print file (an upload, a path, or the bytes with a name) against its order: findings, each with Seshat's
     * decision (fix, ask, refuse, note); explain adds a summary and a text per finding for the customer.
     */
    public function check(UploadedFile|string $file, array $order = [], bool $explain = false, ?string $name = null, ?string $recipe = null): array
    {
        $input = array_filter(['order' => (object) $order, 'explain' => $explain ?: null, 'recipe' => $recipe]);
        if (is_string($file) && preg_match('/^f_[a-z0-9]{24}$/', $file)) {
            return $this->call('check', ['file_id' => $file] + $input);
        }
        $sent = $this->file($file, $name);
        if ($this->size($sent) > self::IN_PARTS_FROM) {
            return $this->call('check', ['file_id' => $this->sendInParts($sent)['file_id']] + $input);
        }

        return $this->call('check', $input, $sent);
    }

    /**
     * The fixes on a checked file: 'auto' (every fix Seshat decided on) or a list like
     * [['fix' => 'scale_to_size', 'option' => 'fit'], ['fix' => 'add_bleed']]. Answers the new file_id, what was done
     * and the check of the fixed file.
     */
    public function fix(string $fileId, string|array|null $fixes = null, array $order = [], ?string $recipe = null): array
    {
        $fixes ??= $recipe === null ? 'auto' : null;

        return $this->call('fix', array_filter(['file_id' => $fileId, 'fixes' => $fixes, 'order' => (object) $order, 'recipe' => $recipe], fn ($v) => $v !== null));
    }

    /** A page separated: every plate (CMYK and spot colours) with its ink coverage, and a proof with overprint through the profile. */
    public function plates(string $fileId, int $page = 1, int $dpi = 100, ?string $profile = null): array
    {
        return $this->call('plates', array_filter(['file_id' => $fileId, 'page' => $page, 'dpi' => $dpi, 'profile' => $profile]));
    }

    /** What changed from an earlier version to this one: words, areas, colours and fonts, with marked pictures. */
    public function compareFiles(string $fileId, string $againstFileId): array
    {
        return $this->call('compare_files', ['file_id' => $fileId, 'against_file_id' => $againstFileId]);
    }

    /**
     * The check as a PDF report for the customer, every problem marked on its page, kept as a file.
     *
     * @param  array<string, string>  $texts  a finding code => your own sentence for it
     */
    public function report(string $fileId, array $order = [], ?string $recipe = null, string $language = 'en', array $texts = []): array
    {
        return $this->call('report', array_filter(['file_id' => $fileId, 'order' => $order ? (object) $order : null, 'recipe' => $recipe, 'language' => $language, 'texts' => $texts ? (object) $texts : null]));
    }

    /** A product's order (on a preset or not) and fixes kept by name; check, fix and report take the recipe's name. Not charged. */
    public function saveRecipe(string $name, array $order = [], string|array|null $fixes = null): array
    {
        return $this->call('recipe_save', array_filter(['name' => $name, 'order' => (object) $order, 'fixes' => $fixes], fn ($v) => $v !== null));
    }

    /** The account's recipes, and the presets to build them on. Not charged. */
    public function recipes(): array
    {
        return $this->call('recipe_list');
    }

    public function deleteRecipe(string $name): array
    {
        return $this->call('recipe_delete', ['name' => $name]);
    }

    /** A page as a PNG (a data: URL) with its trim, bleed and safe zone drawn: a proof to show the customer. */
    public function preview(string $fileId, int $page = 1, int $longest = 1200): array
    {
        return $this->call('preview', ['file_id' => $fileId, 'page' => $page, 'longest' => $longest]);
    }

    /**
     * Two or more kept files as one, in the order given: the new file_id, its pages and the pages per file.
     *
     * @param  list<string>  $fileIds
     */
    public function merge(array $fileIds, ?string $name = null): array
    {
        return $this->call('merge', array_filter(['file_ids' => array_values($fileIds), 'name' => $name]));
    }

    /**
     * A kept file as one file per page range: parts with their range, pages and file_id.
     *
     * @param  list<string>  $ranges  such as ['1-2', '3-end']
     */
    public function split(string $fileId, array $ranges): array
    {
        return $this->call('split', ['file_id' => $fileId, 'ranges' => array_values($ranges)]);
    }

    /** Pages as pictures (png or jpg, 36–600 dpi, pages such as '1-3'), each kept as a file to download. */
    public function render(string $fileId, string $format = 'png', int $dpi = 150, ?string $pages = null): array
    {
        return $this->call('render', array_filter(['file_id' => $fileId, 'format' => $format, 'dpi' => $dpi, 'pages' => $pages]));
    }

    /** Which modules this application may use (preflight, ai_enhance) and the modes: show only what may be used. Not charged. */
    public function modules(): array
    {
        return $this->call('modules');
    }

    /**
     * A file kept by Rekhu for a day (a mask, a background picture, a picture to enhance later): its file_id and its
     * safety report. A file larger than 20 MB goes up in parts. Not charged.
     */
    public function upload(UploadedFile|string $file, ?string $name = null): array
    {
        $sent = $this->file($file, $name);

        return $this->size($sent) > self::IN_PARTS_FROM ? $this->sendInParts($sent) : $this->call('upload', [], $sent);
    }

    /**
     * A file of any size (an upload, a path, or the bytes with a name) sent in parts, read from disk one part at a time,
     * each part tried again up to three times; then checked for safety. $progress gets (bytes sent, total, upload_id),
     * once before the first part and after each part: keep the upload_id to resume. Answers file_id, name, bytes and
     * safety; an unsafe file throws UnsafeFile with the report. Not charged.
     *
     * @param  callable(int, int, string): void|null  $progress
     */
    public function uploadInParts(UploadedFile|string $file, ?string $name = null, ?callable $progress = null, int $timeoutSeconds = 1800, float $everySeconds = 2.0): array
    {
        return $this->sendInParts($this->file($file, $name), $progress, $timeoutSeconds, $everySeconds);
    }

    /**
     * An upload in parts that stopped halfway (the process ended, the connection dropped), finished from the same file:
     * only the parts Rekhu does not have yet are sent. The upload_id is the one uploadInParts gave to $progress; an
     * upload is kept 24 hours. Answers as uploadInParts.
     *
     * @param  callable(int, int, string): void|null  $progress
     */
    public function resumeUpload(string $uploadId, UploadedFile|string $file, ?callable $progress = null, int $timeoutSeconds = 1800, float $everySeconds = 2.0): array
    {
        $status = $this->call('upload_status', ['upload_id' => $uploadId]);
        if (($status['status'] ?? null) === 'open') {
            $sent = $this->file($file, (string) ($status['name'] ?? 'file'));
            if ($this->size($sent) !== (int) $status['bytes']) {
                throw new RekhuException("This file is {$this->size($sent)} bytes; the upload {$uploadId} is for {$status['bytes']} bytes.", 0, 'invalid_request_error');
            }
            $this->sendParts($uploadId, $sent, (int) $status['part_size'], (int) $status['bytes'], array_map('intval', (array) $status['missing']), (int) ($status['bytes_received'] ?? 0), $progress);
        }
        if (in_array($status['status'] ?? null, ['open', 'scanning'], true)) {
            return $this->finish($uploadId, $timeoutSeconds, $everySeconds);
        }

        return $this->ready($status);
    }

    /** An upload in parts dropped with the parts sent so far. Not charged. */
    public function abortUpload(string $uploadId): array
    {
        return $this->call('upload_abort', ['upload_id' => $uploadId]);
    }

    /**
     * ai_enhance: one picture (an upload, a path, bytes with a name, or a file_id from upload) through the modes, in order:
     * upscale, extend, denoise, sharpen, restore, remove_background, replace_background, remove_object, vectorize,
     * colour_correct. options: ppi, width_mm, height_mm (upscale to a print size), factor, all_mm / top_mm… (extend),
     * background ('#hex'), region {x, y, w, h}; mask_file_id and background_file_id from upload(). A large picture, or
     * async: true, answers ['status' => 'queued', 'job_id' => …]: poll() it, or give webhook_url (signed, see
     * Rekhu::verifyWebhook). Charged per mode, only when it worked.
     */
    public function enhance(UploadedFile|string $picture, array|string $modes, array $options = [], array $extra = []): array
    {
        $input = ['mode' => (array) $modes, 'options' => (object) $options] + array_intersect_key($extra, array_flip(['async', 'webhook_url', 'mask_file_id', 'background_file_id']));
        if (is_string($picture) && preg_match('/^f_[a-z0-9]{24}$/', $picture)) {
            return $this->call('enhance', $input + ['file_id' => $picture]);
        }
        $sent = $this->file($picture, $extra['name'] ?? null);
        if ($this->size($sent) > self::IN_PARTS_FROM) {
            return $this->call('enhance', $input + ['file_id' => $this->sendInParts($sent)['file_id']]);
        }

        return $this->call('enhance', $input, $sent);
    }

    /** A small before / after picture of an enhanced file, as a data: URL: what the customer sees before taking it. */
    public function enhancePreview(string $fileId, int $longest = 900): array
    {
        return $this->call('enhance_preview', ['file_id' => $fileId, 'longest' => $longest]);
    }

    /** A job (enhance on a large picture, fillTemplate): open, queued, running with how far, done with its result, or failed with why. */
    public function poll(string $jobId): array
    {
        return $this->call('poll', ['job_id' => $jobId]);
    }

    /** A job polled until it is done or failed (every 2 s, up to 10 minutes by default): its last answer. */
    public function waitFor(string $jobId, int $timeoutSeconds = 600, float $everySeconds = 2.0): array
    {
        $until = time() + $timeoutSeconds;
        while (true) {
            $job = $this->poll($jobId);
            if (in_array($job['status'] ?? null, ['done', 'failed'], true) || time() > $until) {
                return $job;
            }
            usleep((int) ($everySeconds * 1_000_000));
        }
    }

    /**
     * A copy of a template per row: rows are maps ['[name]' => 'Sara'] or lists of ['find' => …, 'replace' => …]. The
     * template is an upload, a path, bytes with a name, or a file_id; long lists go in steps of 1000 by themselves.
     * Answers the job ['status' => 'queued', 'job_id' => …]: waitFor() it for the files (a ZIP or a merged PDF per 250
     * copies) and a report per row. options: output ('zip' | 'merged'), name_by (the find whose value names each file),
     * align ('left' | 'center' | 'right': keep the start, middle or end of each filled text, for centred certificates),
     * webhook_url, name. Charged per copy in which something was replaced.
     */
    public function fillTemplate(UploadedFile|string $template, array $rows, array $options = []): array
    {
        if ($rows === []) {
            throw new RekhuException('rows is a list of rows, one per copy.', 0, 'invalid_request_error');
        }
        $fileId = $this->fileId($template, $options['name'] ?? null);
        $job = null;
        foreach (array_chunk(array_values($rows), 1000) as $i => $chunk) {
            $last = ($i + 1) * 1000 >= count($rows);
            $input = ['rows' => $chunk]
                + ($job ? ['job_id' => $job['job_id']] : array_filter(['file_id' => $fileId, 'output' => $options['output'] ?? 'zip', 'name_by' => $options['name_by'] ?? null, 'align' => $options['align'] ?? null]))
                + ($last ? array_filter(['webhook_url' => $options['webhook_url'] ?? null]) : ['more' => true]);
            $job = $this->call('fill_template', $input);
        }

        return $job;
    }

    /** Where texts sit in a file (an upload, a path, bytes with a name, or a file_id): page, box in points from the bottom left, font and size. */
    public function textPositions(UploadedFile|string $file, string|array $find, bool $matchCase = true, ?string $name = null): array
    {
        return $this->call('text_positions', ['file_id' => $this->fileId($file, $name), 'find' => $find] + ($matchCase ? [] : ['match_case' => false]));
    }

    /** The bytes of a file Seshat made (the fixed PDF). */
    public function download(string $fileId): string
    {
        return $this->client->download('seshat', $fileId, $this->user, $this->tenant);
    }

    /** The same, written straight to a path: for large print files, without holding them in memory. */
    public function downloadTo(string $fileId, string $path): string
    {
        return $this->client->downloadTo('seshat', $fileId, $path, $this->user, $this->tenant);
    }

    /** A file Rekhu keeps for a day, removed now: a customer took it out of their order. */
    public function remove(string $fileId): void
    {
        $this->client->remove('seshat', $fileId, $this->user, $this->tenant);
    }

    /**
     * A font of the library by its PostScript name, as TrueType bytes with its licence (OFL, Apache or UFL, all free to
     * use and embed): for an app that sets text with its own tool. Not charged.
     *
     * @return array{name: string, licence: ?string, bytes: string}
     */
    public function fontFile(string $name): array
    {
        return $this->client->fontFile('seshat', $name);
    }

    /** The same, written straight to a path. */
    public function fontFileTo(string $name, string $path): array
    {
        return $this->client->fontFile('seshat', $name, $path);
    }

    /**
     * Every finding code, decision and fix with its options, versioned: map your own tool's names against it. An "ask"
     * finding carries the same options as ['id' => 'upload_new' | 'print_as_is' | 'fix:<fix>[:<option>]', 'fix'?, 'option'?].
     * Not charged.
     */
    public function codes(): array
    {
        return $this->call('codes');
    }

    /**
     * The fonts embed_fonts can put in: families with their styles, PostScript names and licences, for an editor that
     * lets the customer change text. Not charged.
     */
    public function fonts(): array
    {
        return $this->call('fonts');
    }

    /** A file_id as it is, or the file uploaded first for one. */
    private function fileId(UploadedFile|string $file, ?string $name): string
    {
        if (is_string($file) && preg_match('/^f_[a-z0-9]{24}$/', $file)) {
            return $file;
        }

        return $this->upload($file, $name)['file_id'];
    }

    /** The file sent in parts from the start: upload_start, every part, then the safety check. */
    private function sendInParts(array $file, ?callable $progress = null, int $timeoutSeconds = 1800, float $everySeconds = 2.0): array
    {
        $total = $this->size($file);
        $start = $this->call('upload_start', ['name' => $file['name'], 'bytes' => $total]);
        $this->sendParts($start['upload_id'], $file, (int) $start['part_size'], $total, range(1, (int) $start['parts']), 0, $progress);

        return $this->finish($start['upload_id'], $timeoutSeconds, $everySeconds);
    }

    /**
     * The given parts, one at a time, each read from disk (or cut from the bytes) on its own, so a large file is never
     * whole in memory.
     *
     * @param  list<int>  $parts
     */
    private function sendParts(string $uploadId, array $file, int $partSize, int $total, array $parts, int $sent, ?callable $progress): void
    {
        $handle = isset($file['path']) ? fopen($file['path'], 'rb') : null;
        if ($handle === false) {
            throw new RekhuException("The file {$file['path']} could not be read.", 0, 'invalid_request_error');
        }
        if ($progress) {
            $progress($sent, $total, $uploadId);
        }
        try {
            foreach ($parts as $n) {
                $offset = ($n - 1) * $partSize;
                $length = min($partSize, $total - $offset);
                $bytes = $handle ? (string) stream_get_contents($handle, $length, $offset) : substr($file['bytes'], $offset, $length);
                $this->client->putPart('seshat', $uploadId, $n, $bytes, $this->user, $this->tenant);
                $sent += $length;
                if ($progress) {
                    $progress($sent, $total, $uploadId);
                }
            }
        } finally {
            if ($handle) {
                fclose($handle);
            }
        }
    }

    /** The upload put together and checked for safety, polled until it is ready, refused or failed. */
    private function finish(string $uploadId, int $timeoutSeconds, float $everySeconds): array
    {
        $status = $this->call('upload_complete', ['upload_id' => $uploadId]);
        $until = time() + $timeoutSeconds;
        while (($status['status'] ?? null) === 'scanning') {
            if (time() > $until) {
                throw new RekhuException("The upload {$uploadId} is still being checked; call resumeUpload() later.", 0, 'timeout');
            }
            usleep((int) ($everySeconds * 1_000_000));
            $status = $this->call('upload_status', ['upload_id' => $uploadId]);
        }

        return $this->ready($status);
    }

    /** A finished upload as file_id, name, bytes and safety; refused or failed as the exception for it. */
    private function ready(array $status): array
    {
        if (($status['status'] ?? null) === 'ready') {
            return ['file_id' => $status['file_id'], 'name' => $status['name'] ?? null, 'bytes' => $status['bytes'] ?? null, 'safety' => $status['safety'] ?? null];
        }
        if (($status['status'] ?? null) === 'refused') {
            $why = array_column(array_filter((array) ($status['safety']['findings'] ?? []), fn ($f) => ($f['decision'] ?? null) === 'refuse'), 'about');

            throw new UnsafeFile('The file is not taken: '.($why ? implode('; ', $why) : 'it is not safe').'.', 422, 'refused_unsafe', $status['safety'] ?? null);
        }

        throw new RekhuException((string) ($status['error'] ?? 'The upload is '.($status['status'] ?? 'unknown').'.'), 0, 'upload_failed');
    }

    /** The size of a file as path or bytes. */
    private function size(array $file): int
    {
        return isset($file['path']) ? (int) filesize($file['path']) : strlen((string) $file['bytes']);
    }

    private function file(UploadedFile|string $file, ?string $name): array
    {
        if ($file instanceof UploadedFile) {
            return ['name' => $name ?? $file->getClientOriginalName(), 'path' => $file->getRealPath()];
        }
        if (strlen($file) < 4096 && is_file($file)) {
            return ['name' => $name ?? basename($file), 'path' => $file];
        }
        if ($name === null) {
            throw new RekhuException("Give the file's name with its bytes: check(\$bytes, \$order, name: 'flyer.pdf').", 0, 'invalid_request_error');
        }

        return ['name' => $name, 'bytes' => $file];
    }
}
