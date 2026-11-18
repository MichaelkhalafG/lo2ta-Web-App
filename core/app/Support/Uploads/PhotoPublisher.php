<?php

namespace App\Support\Uploads;

use App\Models\UnitPhoto;
use Illuminate\Support\Facades\Storage;

/**
 * The only way a photo becomes public (PLAN 2.2.15): a copy of the private WebP under a NEW random name on the public
 * disk. Publishing a unit calls publish() for each photo that is not hidden; hiding a photo calls withdraw(), which
 * deletes the public copy, so an old link stops working at once.
 */
final class PhotoPublisher
{
    public function publish(UnitPhoto $photo): void
    {
        if ($photo->is_hidden || is_string($photo->public_path)) {
            return;
        }
        $bytes = Storage::disk(UploadService::PRIVATE_DISK)->get($photo->path);
        if (! is_string($bytes)) {
            return;
        }
        $public = UploadService::randomName('units', 'webp');
        Storage::disk(UploadService::PUBLIC_DISK)->put($public, $bytes);
        $photo->forceFill(['public_path' => $public])->save();
    }

    public function withdraw(UnitPhoto $photo): void
    {
        if (is_string($photo->public_path)) {
            Storage::disk(UploadService::PUBLIC_DISK)->delete($photo->public_path);
            $photo->forceFill(['public_path' => null])->save();
        }
    }
}
