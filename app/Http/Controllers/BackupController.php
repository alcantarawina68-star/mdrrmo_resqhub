<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): View
    {
        return view('dashboard.backup', [
            'preview' => $this->backups->preview(),
            'excluded' => BackupService::EXCLUDED_TABLES,
        ]);
    }

    /**
     * Build the archive and hand it straight to the operator.
     *
     * deleteFileAfterSend removes the working copy once the response has been
     * written, so a full database dump never sits on the server afterwards.
     */
    public function run(Request $request): BinaryFileResponse
    {
        $backup = $this->backups->create($request->user());

        $response = response()->download(
            $backup['path'],
            $backup['filename'],
            ['Content-Type' => 'application/zip'],
        )->deleteFileAfterSend();

        // A dump of every user record must never reach a shared cache. This is
        // set after construction because Symfony adds a "public" directive to a
        // file response it considers cacheable, which would undo a plain
        // "private" in the array above.
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->setPrivate();

        return $response;
    }
}
