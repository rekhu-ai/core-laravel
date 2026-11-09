<?php

declare(strict_types=1);

namespace Rekhu\Core\Knowers;

use Illuminate\Http\UploadedFile;
use Rekhu\Core\Client;
use Rekhu\Core\Exceptions\RekhuException;

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
 */
class Seshat extends Knower
{
    public function __construct(Client $client)
    {
        parent::__construct($client, 'seshat');
    }

    /**
     * A print file (an upload, a path, or the bytes with a name) against its order: findings, each with Seshat's
     * decision (fix, ask, refuse, note); explain adds a summary and a text per finding for the customer.
     */
    public function check(UploadedFile|string $file, array $order = [], bool $explain = false, ?string $name = null): array
    {
        return $this->call('check', array_filter(['order' => (object) $order, 'explain' => $explain ?: null]), $this->file($file, $name));
    }

    /**
     * The fixes on a checked file: 'auto' (every fix Seshat decided on) or a list like
     * [['fix' => 'scale_to_size', 'option' => 'fit'], ['fix' => 'add_bleed']]. Answers the new file_id, what was done
     * and the check of the fixed file.
     */
    public function fix(string $fileId, string|array $fixes = 'auto', array $order = []): array
    {
        return $this->call('fix', ['file_id' => $fileId, 'fixes' => $fixes, 'order' => (object) $order]);
    }

    /** A page as a PNG (a data: URL) with its trim, bleed and safe zone drawn: a proof to show the customer. */
    public function preview(string $fileId, int $page = 1, int $longest = 1200): array
    {
        return $this->call('preview', ['file_id' => $fileId, 'page' => $page, 'longest' => $longest]);
    }

    /** Which modules this application may use (preflight, ai_enhance) and the modes: show only what may be used. Not charged. */
    public function modules(): array
    {
        return $this->call('modules');
    }

    /** A file kept by Rekhu for a day (a mask, a background picture, a picture to enhance later): its file_id. Not charged. */
    public function upload(UploadedFile|string $file, ?string $name = null): array
    {
        return $this->call('upload', [], $this->file($file, $name));
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

        return $this->call('enhance', $input, $this->file($picture, $extra['name'] ?? null));
    }

    /** A small before / after picture of an enhanced file, as a data: URL: what the customer sees before taking it. */
    public function enhancePreview(string $fileId, int $longest = 900): array
    {
        return $this->call('enhance_preview', ['file_id' => $fileId, 'longest' => $longest]);
    }

    /** A job (enhance on a large picture): queued, running, done with its result, or failed with why. */
    public function poll(string $jobId): array
    {
        return $this->call('poll', ['job_id' => $jobId]);
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
     * Every finding code, decision and fix with its options, versioned: map your own tool's names against it. An "ask"
     * finding carries the same options as ['id' => 'upload_new' | 'print_as_is' | 'fix:<fix>[:<option>]', 'fix'?, 'option'?].
     * Not charged.
     */
    public function codes(): array
    {
        return $this->call('codes');
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
