<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Support\Activity\ActivityLogger;
use App\Support\Review\DocumentAccess;
use App\Support\Uploads\ServeFile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff access to a unit's private files (PLAN 2.2.15): signed-in, active staff with the view-documents ability.
 * Every opening of a document is logged, so access to national ID copies can be audited. A draft's files answer
 * "not found" to every staff role (Michael's answer to Q26, 2026-11-28; D117): a draft is still the seller's alone.
 */
class FilesController extends Controller
{
    public function document(Request $request, string $document, ActivityLogger $log): StreamedResponse
    {
        $row = UnitDocument::query()->where('public_id', $document)->with('unit')->firstOrFail();
        if (! is_string($row->path) || $row->purged_at !== null) {
            abort(404);
        }
        if (DocumentAccess::isDraftFile($row->unit)) {
            $log->record('document.view_refused', $row->unit, null, ['type' => $row->type->value, 'document' => $row->public_id, 'draft' => true]);
            abort(404);
        }
        // G2: national ID copies are for both roles only while the unit is under review or in an open deal.
        $admin = $request->user('admin');
        if (! $admin instanceof AdminUser || ! DocumentAccess::mayOpen($admin, $row)) {
            $log->record('document.view_refused', $row->unit, null, ['type' => $row->type->value, 'document' => $row->public_id]);
            abort(403);
        }
        $log->record('document.viewed', $row->unit, null, ['type' => $row->type->value, 'document' => $row->public_id]);

        return ServeFile::private($row->path, $row->mime, $row->downloadName());
    }

    public function photo(string $photo): StreamedResponse
    {
        $row = UnitPhoto::query()->where('public_id', $photo)->with('unit:id,state')->firstOrFail();
        if (DocumentAccess::isDraftFile($row->unit)) {
            abort(404);
        }

        return ServeFile::private($row->path, 'image/webp', 'photo-'.strtolower(substr($row->public_id, -6)).'.webp');
    }
}
