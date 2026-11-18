<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Support\Activity\ActivityLogger;
use App\Support\Uploads\ServeFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff access to a unit's private files (PLAN 2.2.15): signed-in, active staff with the view-documents ability.
 * Every opening of a document is logged, so access to national ID copies can be audited.
 */
class FilesController extends Controller
{
    public function document(string $document, ActivityLogger $log): StreamedResponse
    {
        $row = UnitDocument::query()->where('public_id', $document)->firstOrFail();
        if (! is_string($row->path)) {
            abort(404);
        }
        $log->record('document.viewed', $row->unit, null, ['type' => $row->type->value, 'document' => $row->public_id]);

        return ServeFile::private($row->path, $row->mime, $row->downloadName());
    }

    public function photo(string $photo): StreamedResponse
    {
        $row = UnitPhoto::query()->where('public_id', $photo)->firstOrFail();

        return ServeFile::private($row->path, 'image/webp', 'photo-'.strtolower(substr($row->public_id, -6)).'.webp');
    }
}
