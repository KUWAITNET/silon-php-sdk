<?php

declare(strict_types=1);

namespace Silon\Resource;

use Silon\Model\BulkFileList;
use Silon\Model\BulkFileUpload;

/**
 * `$client->bulk->files` — the CSV ingestion path that feeds
 * `messages.sendBatch`'s file form.
 */
final class BulkFiles extends Resource
{
    private const PATH = '/api/v1/bulk/files/';

    /** List saved CSVs (`{count, results}`). */
    public function list(): BulkFileList
    {
        return new BulkFileList($this->client->get(self::PATH));
    }

    /**
     * Upload a CSV for later batch/bulk sends
     * (multipart field name `file`, content type `text/csv`).
     *
     * `$file` may be a path to a CSV (a string that names an existing file),
     * raw CSV content (any other string), an open stream resource, or a
     * `[filename, content]` pair. `$filename` overrides the sent filename.
     *
     * @param string|resource|array{0:string,1:string} $file
     */
    public function upload(mixed $file, ?string $filename = null): BulkFileUpload
    {
        [$name, $content] = self::prepareFile($file, $filename);
        $data = $this->client->post(self::PATH, [
            'multipart' => [[
                'name' => 'file',
                'contents' => $content,
                'filename' => $name,
                'headers' => ['Content-Type' => 'text/csv'],
            ]],
        ]);

        return new BulkFileUpload($data);
    }

    /**
     * @param string|resource|array{0:string,1:string} $file
     * @return array{0:string,1:string|resource}
     */
    private static function prepareFile(mixed $file, ?string $filename): array
    {
        if (is_array($file)) {
            return [$filename ?? (string) $file[0], $file[1]];
        }
        if (is_resource($file)) {
            $meta = stream_get_meta_data($file);
            $uri = is_string($meta['uri'] ?? null) ? basename($meta['uri']) : 'recipients.csv';

            return [$filename ?? $uri, $file];
        }
        if (is_string($file) && is_file($file)) {
            return [$filename ?? basename($file), (string) file_get_contents($file)];
        }

        return [$filename ?? 'recipients.csv', (string) $file];
    }
}
