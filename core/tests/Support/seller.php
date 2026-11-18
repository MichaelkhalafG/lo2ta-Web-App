<?php

use App\Enums\DeclarationKind;
use App\Enums\DocumentType;
use App\Enums\PhotoKind;
use App\Enums\UnitState;
use App\Models\Unit;
use App\Models\User;
use App\Support\Seller\Declarations;
use App\Support\Uploads\UploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Helpers for the seller wizard tests (tests/Feature/Seller). Images are drawn with GD at test time; nothing here is a
| real document or a real person's data.
*/

/**
 * Keyed API headers plus the seller's token and the visitor IP the Next server forwards.
 *
 * @return array<string, string>
 */
function sellerHeaders(User $user, string $ip = '203.0.113.10'): array
{
    return [...apiHeaders(), 'Authorization' => 'Bearer '.$user->createToken('web')->plainTextToken, 'X-Client-Ip' => $ip];
}

/** A PDF fixture made by pypdf (scripts/fixtures/make-pdf-fixtures.py). */
function pdfFixture(string $name): string
{
    return (string) file_get_contents(base_path('tests/fixtures/uploads/pdf/'.$name));
}

function testImage(string $type, int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    for ($x = 0; $x < $width; $x += 40) {
        imagefilledrectangle($image, $x, 0, $x + 19, $height - 1, (int) imagecolorallocate($image, ($x * 7) % 255, 90, 160));
    }
    ob_start();
    match ($type) {
        'png' => imagepng($image),
        'webp' => imagewebp($image),
        'gif' => imagegif($image),
        default => imagejpeg($image, null, 85),
    };

    return (string) ob_get_clean();
}

/** A JPEG with an EXIF block carrying a GPS position, as a phone writes it. */
function jpegWithGps(int $width = 1200, int $height = 900): string
{
    $jpeg = testImage('jpeg', $width, $height);
    $exif = "Exif\0\0".'MM'."\0*\0\0\0\x08".'GPSLatitude 30.0444 GPSLongitude 31.2357 lokta-test-location';
    $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

    return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
}

/** A PNG whose header claims a huge size over a tiny body: a decompression bomb if decoded. */
function pngBomb(int $width = 30000, int $height = 30000): string
{
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $ihdr = pack('NN', $width, $height)."\x08\x02\x00\x00\x00";

    return "\x89PNG\r\n\x1A\n".$chunk('IHDR', $ihdr).$chunk('IDAT', (string) gzcompress(str_repeat("\0", 1024))).$chunk('IEND', '');
}

function upload(string $name, string $bytes, string $clientType = 'application/octet-stream'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $bytes)->mimeType($clientType);
}

function newDraft(User $user): string
{
    return (string) test()->postJson('/api/v1/me/units', ['no_over' => true], sellerHeaders($user))->assertCreated()->json('data.id');
}

/**
 * A draft with every step complete: the data, the four required documents and one photo, files on the fake disk.
 *
 * @param  array<string, mixed>  $overrides
 */
function completeDraft(User $user, array $overrides = []): Unit
{
    $unit = Unit::factory()->create([
        'seller_id' => $user->id, 'state' => UnitState::Draft, 'contract_month' => 4, 'delivery_month' => 6, ...$overrides,
    ]);
    Declarations::record($unit, $user, DeclarationKind::NoOver, '203.0.113.10');
    foreach ([DocumentType::Contract, DocumentType::Schedule, DocumentType::NationalIdFront, DocumentType::NationalIdBack] as $type) {
        $path = UploadService::randomName('documents', 'pdf');
        Storage::disk(UploadService::PRIVATE_DISK)->put($path, '%PDF-1.7 test');
        $unit->documents()->create([
            'type' => $type, 'path' => $path, 'original_name' => $type->value.'.pdf', 'mime' => 'application/pdf',
            'bytes' => 13, 'sha256' => hash('sha256', $path),
        ]);
    }
    $photo = UploadService::randomName('photos', 'webp');
    Storage::disk(UploadService::PRIVATE_DISK)->put($photo, testImage('webp', 1200, 900));
    $unit->photos()->create(['kind' => PhotoKind::Photo, 'path' => $photo, 'sort' => 1, 'width' => 1200, 'height' => 900, 'bytes' => 1]);

    return $unit->refresh();
}

/**
 * @return array{declarations: array<string, bool>}
 */
function declarations(): array
{
    return ['declarations' => ['owner_not_broker' => true, 'data_matches' => true, 'terms_privacy' => true]];
}
