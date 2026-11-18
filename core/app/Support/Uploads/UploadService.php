<?php

namespace App\Support\Uploads;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PhotoKind;
use App\Models\DuplicateAttempt;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Support\Uploads\Pdf\PdfInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Every file the public sends (PLAN 2.2.15, Michael 2026-11-18). In order, and nothing is stored before all pass:
 * 1. the upload itself is valid and its size on disk (not what the client claims) is within upload_max_mb;
 * 2. its type is sniffed from the content (FileSniffer); the extension and the client's Content-Type are ignored;
 * 3. the per-unit count for its kind is not reached;
 * 4. images are decoded and re-encoded (ImageSanitizer), PDFs are inspected (PdfInspector);
 * 5. the result is written under a random 40-character name on the PRIVATE disk, then the row is created; if the row
 *    fails, the file is removed.
 */
final class UploadService
{
    public const PRIVATE_DISK = 'local';

    public const PUBLIC_DISK = 'public';

    private const DOCUMENT_TYPES = [FileSniffer::PDF, FileSniffer::JPEG, FileSniffer::PNG];

    private const PHOTO_TYPES = [FileSniffer::JPEG, FileSniffer::PNG, FileSniffer::WEBP];

    public function __construct(
        private readonly Settings $settings,
        private readonly ImageSanitizer $images,
        private readonly PdfInspector $pdfs,
    ) {}

    public function document(Unit $unit, User $user, DocumentType $type, UploadedFile $file): UnitDocument
    {
        [$path, $mime] = $this->accept($file, self::DOCUMENT_TYPES);

        $replaces = null;
        if (in_array($type, UnitDocument::SINGLE, true)) {
            $replaces = $unit->documents()->where('type', $type->value)->first();
        } elseif ($unit->documents()->where('type', $type->value)->count() >= $this->documentCap($type)) {
            throw new UploadRefused('count_reached', ['max' => $this->documentCap($type)]);
        }

        $flags = [];
        if ($mime === FileSniffer::PDF) {
            $bytes = (string) file_get_contents($path);
            $flags = $this->pdfs->inspect($bytes);
            $ext = 'pdf';
        } else {
            $out = $this->images->document($path, $mime);
            [$bytes, $mime, $ext] = [$out['bytes'], $out['mime'], $out['ext']];
        }
        $sha = (string) hash_file('sha256', $path);

        $this->ensureAccountRoom($unit, strlen($bytes), $replaces->bytes ?? 0);
        $stored = self::store(self::PRIVATE_DISK, 'documents', $ext, $bytes);
        try {
            $document = DB::transaction(function () use ($unit, $type, $file, $stored, $mime, $bytes, $sha, $flags, $replaces): UnitDocument {
                $document = $unit->documents()->create([
                    'type' => $type,
                    'status' => DocumentStatus::Uploaded,
                    'path' => $stored,
                    'original_name' => self::displayName($file->getClientOriginalName()),
                    'mime' => $mime,
                    'bytes' => strlen($bytes),
                    'sha256' => $sha,
                    'flags' => $flags === [] ? null : $flags,
                ]);
                $replaces?->delete();

                return $document;
            });
        } catch (Throwable $e) {
            Storage::disk(self::PRIVATE_DISK)->delete($stored);

            throw $e;
        }
        if ($replaces instanceof UnitDocument && is_string($replaces->path)) {
            Storage::disk(self::PRIVATE_DISK)->delete($replaces->path);
        }

        // PLAN 3.4 B: the same file on another unit is a warning for the admin, never shown to the seller.
        $match = UnitDocument::query()->where('sha256', $sha)->where('unit_id', '!=', $unit->id)->value('unit_id');
        if (is_int($match)) {
            DuplicateAttempt::query()->create([
                'user_id' => $user->id, 'unit_id' => $unit->id, 'matched_unit_id' => $match,
                'key_kind' => 'file_sha256', 'stage' => 'upload', 'blocked' => false,
            ]);
        }

        return $document;
    }

    public function photo(Unit $unit, PhotoKind $kind, bool $projectImage, UploadedFile $file): UnitPhoto
    {
        [$path, $mime] = $this->accept($file, self::PHOTO_TYPES);

        $replaces = null;
        if ($kind === PhotoKind::FloorPlan) {
            $replaces = $unit->photos()->where('kind', PhotoKind::FloorPlan->value)->first();
        } elseif ($unit->photos()->where('kind', PhotoKind::Photo->value)->count() >= $this->settings->int('photo_max_count')) {
            throw new UploadRefused('count_reached', ['max' => $this->settings->int('photo_max_count')]);
        }

        $out = $this->images->photo($path, $mime);
        $this->ensureAccountRoom($unit, strlen($out['bytes']), $replaces->bytes ?? 0);
        $stored = self::store(self::PRIVATE_DISK, 'photos', $out['ext'], $out['bytes']);
        try {
            $photo = DB::transaction(function () use ($unit, $kind, $projectImage, $stored, $out, $replaces): UnitPhoto {
                $last = $unit->photos()->where('kind', PhotoKind::Photo->value)->max('sort');
                $sort = $kind === PhotoKind::FloorPlan ? 0 : (is_numeric($last) ? max(0, (int) $last) : 0) + 1;
                $photo = $unit->photos()->create([
                    'kind' => $kind,
                    'is_project_image' => $kind === PhotoKind::Photo && $projectImage,
                    'is_hidden' => false,
                    'path' => $stored,
                    'sort' => $sort,
                    'width' => $out['width'],
                    'height' => $out['height'],
                    'bytes' => strlen($out['bytes']),
                ]);
                $replaces?->delete();

                return $photo;
            });
        } catch (Throwable $e) {
            Storage::disk(self::PRIVATE_DISK)->delete($stored);

            throw $e;
        }
        if ($replaces instanceof UnitPhoto) {
            self::deletePhotoFiles($replaces);
        }

        return $photo;
    }

    public static function deletePhotoFiles(UnitPhoto $photo): void
    {
        Storage::disk(self::PRIVATE_DISK)->delete($photo->path);
        if (is_string($photo->public_path)) {
            Storage::disk(self::PUBLIC_DISK)->delete($photo->public_path);
        }
    }

    /** A random name that says nothing about the unit, the seller or the uploaded file. */
    public static function randomName(string $folder, string $ext): string
    {
        return $folder.'/'.bin2hex(random_bytes(20)).'.'.$ext;
    }

    /**
     * @param  list<string>  $allowed
     * @return array{0: string, 1: string} the temporary path and the sniffed type
     */
    private function accept(UploadedFile $file, array $allowed): array
    {
        if (! $file->isValid()) {
            throw new UploadRefused('upload_failed');
        }
        $path = $file->getRealPath();
        $size = $path === false ? false : @filesize($path);
        if ($path === false || $size === false || $size === 0) {
            throw new UploadRefused('upload_failed');
        }
        $maxMb = $this->settings->int('upload_max_mb');
        if ($size > $maxMb * 1024 * 1024) {
            throw new UploadRefused('file_too_large', ['mb' => $maxMb]);
        }
        $mime = FileSniffer::detect($path);
        if ($mime === null || ! in_array($mime, $allowed, true)) {
            throw new UploadRefused($allowed === self::DOCUMENT_TYPES ? 'document_type' : 'photo_type');
        }

        return [$path, $mime];
    }

    /**
     * The account's stored uploads, all its units together, must stay within upload_max_account_mb. A file that
     * replaces another (an ID side, the floor plan) only counts the difference.
     */
    private function ensureAccountRoom(Unit $unit, int $adding, int $freeing): void
    {
        $units = Unit::query()->where('seller_id', $unit->seller_id)->pluck('id');
        $used = (int) UnitDocument::query()->whereIn('unit_id', $units)->whereNotNull('path')->sum('bytes')
            + (int) UnitPhoto::query()->whereIn('unit_id', $units)->sum('bytes');
        $capMb = $this->settings->int('upload_max_account_mb');
        if ($used - $freeing + $adding > $capMb * 1024 * 1024) {
            throw new UploadRefused('account_storage_full', ['mb' => $capMb]);
        }
    }

    private function documentCap(DocumentType $type): int
    {
        return match ($type) {
            DocumentType::Contract => $this->settings->int('doc_max_contract'),
            DocumentType::Schedule => $this->settings->int('doc_max_schedule'),
            DocumentType::Receipt => $this->settings->int('doc_max_receipt'),
            DocumentType::Statement => $this->settings->int('doc_max_statement'),
            DocumentType::NationalIdFront, DocumentType::NationalIdBack => 1,
        };
    }

    private static function store(string $disk, string $folder, string $ext, string $bytes): string
    {
        $path = self::randomName($folder, $ext);
        if (! Storage::disk($disk)->put($path, $bytes)) {
            throw new UploadRefused('upload_failed');
        }

        return $path;
    }

    /** For display to the owner and staff only; never in a path, a header or a public answer. */
    private static function displayName(string $name): string
    {
        $clean = (string) preg_replace('/[^\p{L}\p{N} ._()-]+/u', '', $name);
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        return mb_substr($clean === '' ? 'file' : $clean, 0, 120);
    }
}
