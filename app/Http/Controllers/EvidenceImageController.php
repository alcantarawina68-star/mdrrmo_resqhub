<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams evidence bytes out of the database.
 *
 * Public on purpose, matching the world-readable /storage/evidence/ URLs it
 * replaces: the public incident page has always shown evidence thumbnails.
 */
class EvidenceImageController extends Controller
{
    public function __invoke(Request $request, Evidence $evidence): Response
    {
        $file = $evidence->file;

        if ($file === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $content = (string) $file->content;
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($content, Response::HTTP_OK, [
            'Content-Type' => $this->mimeType($evidence, $content),
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => $disposition.'; filename="'.$this->safeFilename($evidence).'"',
            'Cache-Control' => 'private, max-age=86400',
            'ETag' => '"'.md5($content).'"',
        ]);
    }

    /**
     * Evidence written before mime detection always carried a bare extension
     * such as "png", which is not a renderable Content-Type.
     */
    private function mimeType(Evidence $evidence, string $content): string
    {
        if (str_contains($evidence->file_type, '/')) {
            return $evidence->file_type;
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);

        return is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
    }

    private function safeFilename(Evidence $evidence): string
    {
        return str_replace(['"', "\r", "\n", '/', '\\'], '', $evidence->original_name);
    }
}
