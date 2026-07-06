<?php

declare(strict_types=1);

namespace Silon\Http;

/**
 * Builds a `multipart/form-data` body from a list of parts. Used for the CSV
 * upload endpoint; kept transport-agnostic so the exact bytes are identical
 * whatever {@see HttpClientInterface} sends them.
 *
 * @internal
 */
final class MultipartBody
{
    /**
     * @param list<array{name:string,contents:string,filename?:string,headers?:array<string,string>}> $parts
     * @return array{0:string,1:string} the encoded body and the Content-Type header value
     */
    public static function build(array $parts): array
    {
        $boundary = '----SilonBoundary' . bin2hex(random_bytes(16));
        $crlf = "\r\n";
        $body = '';

        foreach ($parts as $part) {
            $disposition = 'Content-Disposition: form-data; name="' . $part['name'] . '"';
            if (isset($part['filename'])) {
                $disposition .= '; filename="' . $part['filename'] . '"';
            }
            $body .= '--' . $boundary . $crlf;
            $body .= $disposition . $crlf;
            foreach ($part['headers'] ?? [] as $headerName => $headerValue) {
                $body .= $headerName . ': ' . $headerValue . $crlf;
            }
            $body .= $crlf;
            $body .= $part['contents'] . $crlf;
        }
        $body .= '--' . $boundary . '--' . $crlf;

        return [$body, 'multipart/form-data; boundary=' . $boundary];
    }
}
