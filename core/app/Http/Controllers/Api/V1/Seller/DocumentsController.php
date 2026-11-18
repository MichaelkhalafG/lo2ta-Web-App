<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\DocumentType;
use App\Enums\UnitState;
use App\Http\Controllers\Api\V1\Account\MeController;
use App\Http\Controllers\Controller;
use App\Support\Accounts\ClientIp;
use App\Support\Activity\ActivityLogger;
use App\Support\Mail\TeamNotice;
use App\Support\Seller\SellerUnits;
use App\Support\Seller\Throttle;
use App\Support\Uploads\ServeFile;
use App\Support\Uploads\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Step 4 documents (PLAN 2.2.2, 2.2.15). Upload at any time while the unit is alive (new receipts after publishing
 * too, PLAN 2.2.7); delete only while the seller can still edit the steps. Only the owner reaches these routes; staff
 * read documents through the dashboard.
 */
class DocumentsController extends Controller
{
    private const CLOSED = [UnitState::Rejected, UnitState::Withdrawn, UnitState::Sold];

    public function store(Request $request, string $unit, UploadService $uploads, Throttle $throttle, TeamNotice $team, ActivityLogger $log): JsonResponse
    {
        $user = MeController::user($request);
        $model = SellerUnits::unit($user, $unit);
        $throttle->hit('upload', ['rl_upload_user' => 'u:'.$user->id, 'rl_upload_ip' => 'ip:'.ClientIp::of($request)]);
        if (in_array($model->state, self::CLOSED, true)) {
            throw ValidationException::withMessages(['unit' => UnitsController::text('seller.not_editable')]);
        }
        // The file rule only asks PHP whether an upload arrived; its type and size are decided from the content.
        $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)],
            'file' => ['required', 'file'],
        ]);

        $file = $request->file('file');
        $type = DocumentType::from($request->string('type')->toString());
        $document = $uploads->document($model, $user, $type, is_array($file) ? $file[0] : $file);
        $log->record('document.uploaded', $model, null, ['type' => $type->value, 'flags' => $document->flags]);

        if (in_array($model->state, [UnitState::Submitted, UnitState::Published, UnitState::Reserved], true) && $model->serial !== null) {
            $team->send('admin.unit_submitted', ['reference' => sprintf('#%04d', $model->serial), 'documents' => '1']);
        }

        return response()->json(['data' => [
            'id' => $document->public_id,
            'type' => $document->type->value,
            'status' => $document->status->value,
            'name' => $document->original_name,
            'mime' => $document->mime,
            'bytes' => $document->bytes,
        ]], 201);
    }

    public function show(Request $request, string $unit, string $document): StreamedResponse
    {
        $model = SellerUnits::unit(MeController::user($request), $unit);
        $row = SellerUnits::document($model, $document);
        if (! is_string($row->path)) {
            abort(404);
        }

        return ServeFile::private($row->path, $row->mime, $row->downloadName());
    }

    public function destroy(Request $request, string $unit, string $document, ActivityLogger $log): JsonResponse
    {
        $model = SellerUnits::unit(MeController::user($request), $unit);
        UnitsController::mustBeEditable($model);
        $row = SellerUnits::document($model, $document);
        $path = $row->path;
        $log->record('document.deleted', $model, ['type' => $row->type->value]);
        $row->delete();
        if (is_string($path)) {
            Storage::disk(UploadService::PRIVATE_DISK)->delete($path);
        }

        return response()->json(['ok' => true]);
    }
}
