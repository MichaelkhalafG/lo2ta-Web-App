<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\PhotoKind;
use App\Enums\UnitState;
use App\Http\Controllers\Api\V1\Account\MeController;
use App\Http\Controllers\Controller;
use App\Http\Resources\SellerUnitResource;
use App\Models\Unit;
use App\Models\UnitPhoto;
use App\Support\Accounts\ClientIp;
use App\Support\Activity\ActivityLogger;
use App\Support\Seller\SellerUnits;
use App\Support\Seller\Throttle;
use App\Support\Uploads\PhotoPublisher;
use App\Support\Uploads\ServeFile;
use App\Support\Uploads\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Step 5 photos and floor plan (PLAN 2.2.2, 2.2.15). Changed only while the seller can edit the steps; editing a
 * published unit's photos is the later "editing after publish" item (PLAN 2.2.7).
 */
class PhotosController extends Controller
{
    public function store(Request $request, string $unit, UploadService $uploads, Throttle $throttle, ActivityLogger $log): JsonResponse
    {
        $user = MeController::user($request);
        $model = SellerUnits::unit($user, $unit);
        $throttle->hit('upload', ['rl_upload_user' => 'u:'.$user->id, 'rl_upload_ip' => 'ip:'.ClientIp::of($request)]);
        self::mustAllowPhotos($model);
        $request->validate([
            'kind' => ['required', Rule::enum(PhotoKind::class)],
            'is_project_image' => ['sometimes', 'boolean'],
            'file' => ['required', 'file'],
        ]);

        $file = $request->file('file');
        $kind = PhotoKind::from($request->string('kind')->toString());
        $photo = $uploads->photo($model, $kind, $request->boolean('is_project_image'), is_array($file) ? $file[0] : $file);
        $log->record('photo.uploaded', $model, null, ['kind' => $kind->value]);
        self::afterLiveChange($model, $photo);

        return (new SellerUnitResource($model->refresh()))->response()->setStatusCode(201);
    }

    /** Order (the first is the cover) and the "Project image" label (PLAN 2.2.9 point 7). */
    public function update(Request $request, string $unit, ActivityLogger $log): SellerUnitResource
    {
        $model = SellerUnits::unit(MeController::user($request), $unit);
        self::mustAllowPhotos($model);
        $request->validate([
            'order' => ['required', 'array', 'max:50'],
            'order.*' => ['string', 'distinct', 'regex:/^'.SellerUnits::ID.'$/'],
            'project_images' => ['sometimes', 'array', 'max:50'],
            'project_images.*' => ['string', 'distinct'],
        ]);

        $photos = $model->photos()->where('kind', PhotoKind::Photo->value)->get()->keyBy('public_id');
        $order = self::strings($request->input('order'));
        if (count($order) !== $photos->count() || array_diff($order, $photos->keys()->all()) !== []) {
            throw ValidationException::withMessages(['order' => UnitsController::text('seller.rules.photo_order')]);
        }
        $project = $request->has('project_images')
            ? self::strings($request->input('project_images'))
            : $photos->filter(fn (UnitPhoto $p): bool => $p->is_project_image)->keys()->all();

        DB::transaction(function () use ($order, $photos, $project): void {
            foreach ($order as $i => $id) {
                $photo = $photos->get($id);
                if ($photo instanceof UnitPhoto) {
                    $photo->forceFill(['sort' => $i + 1, 'is_project_image' => in_array($id, $project, true)])->save();
                }
            }
        });
        $log->record('photo.reordered', $model);
        self::afterLiveChange($model, null);

        return new SellerUnitResource($model->refresh());
    }

    /**
     * Photos change while the steps are editable, and on a published or reserved unit as a free edit (PLAN 2.2.7).
     */
    private static function mustAllowPhotos(Unit $unit): void
    {
        if (! $unit->isEditableBySeller() && ! in_array($unit->state, [UnitState::Published, UnitState::Reserved], true)) {
            throw ValidationException::withMessages(['unit' => UnitsController::text('seller.not_editable')]);
        }
    }

    /**
     * On a live unit, a new photo is public at once and every photo change goes on the admin's "check after edit"
     * list (PLAN 2.2.7: the admin can revert).
     */
    private static function afterLiveChange(Unit $unit, ?UnitPhoto $added): void
    {
        if (! in_array($unit->state, [UnitState::Published, UnitState::Reserved], true)) {
            return;
        }
        if ($added instanceof UnitPhoto) {
            app(PhotoPublisher::class)->publish($added);
        }
        $unit->forceFill(['recheck_after_edit_at' => now()])->save();
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    public function show(Request $request, string $unit, string $photo): StreamedResponse
    {
        $model = SellerUnits::unit(MeController::user($request), $unit);
        $row = SellerUnits::photo($model, $photo);

        return ServeFile::private($row->path, 'image/webp', $row->kind->value.'-'.strtolower(substr($row->public_id, -6)).'.webp');
    }

    public function destroy(Request $request, string $unit, string $photo, ActivityLogger $log): SellerUnitResource
    {
        $model = SellerUnits::unit(MeController::user($request), $unit);
        self::mustAllowPhotos($model);
        $row = SellerUnits::photo($model, $photo);
        $log->record('photo.deleted', $model, ['kind' => $row->kind->value]);
        $row->delete();
        UploadService::deletePhotoFiles($row);
        self::afterLiveChange($model, null);

        return new SellerUnitResource($model->refresh());
    }
}
