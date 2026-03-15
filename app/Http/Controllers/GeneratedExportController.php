<?php

namespace App\Http\Controllers;

use App\Models\GeneratedExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GeneratedExportController extends Controller
{
    public function download(Request $request, GeneratedExport $generatedExport): BinaryFileResponse
    {
        abort_unless(
            $generatedExport->status === 'completed'
                && ($generatedExport->user_id === $request->user()?->id || $request->user()?->hasPermission('activity_logs.view')),
            403
        );

        abort_unless($generatedExport->path && Storage::disk($generatedExport->disk)->exists($generatedExport->path), 404);

        return response()->download(
            Storage::disk($generatedExport->disk)->path($generatedExport->path),
            $generatedExport->file_name ?? basename($generatedExport->path)
        );
    }
}