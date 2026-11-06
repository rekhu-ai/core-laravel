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

    /** The bytes of a file Seshat made (the fixed PDF). */
    public function download(string $fileId): string
    {
        return $this->client->download('seshat', $fileId);
    }

    private function file(UploadedFile|string $file, ?string $name): array
    {
        if ($file instanceof UploadedFile) {
            return ['name' => $name ?? $file->getClientOriginalName(), 'bytes' => (string) file_get_contents($file->getRealPath())];
        }
        if (strlen($file) < 4096 && is_file($file)) {
            return ['name' => $name ?? basename($file), 'bytes' => (string) file_get_contents($file)];
        }
        if ($name === null) {
            throw new RekhuException("Give the file's name with its bytes: check(\$bytes, \$order, name: 'flyer.pdf').", 0, 'invalid_request_error');
        }

        return ['name' => $name, 'bytes' => $file];
    }
}
