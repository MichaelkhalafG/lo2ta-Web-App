<?php

use App\Enums\DocumentType;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\Unit;
use App\Models\UnitDocument;
use App\Models\UnitPhoto;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Support\Uploads\Hardening;
use App\Support\Uploads\PhotoPublisher;
use App\Support\Uploads\UploadService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| Michael's upload rules (2026-11-18, PLAN 2.2.15), one test or more each:
| 1 type from content only · 2 photos re-encoded, original never kept · 3 documents private, random names, served
| only to owner or staff · 4 nothing executable · 5 size, count and pixel limits before processing · 6 PDFs checked
| · 7 rate limits per user and per IP · 8 ownership on every endpoint.
*/

beforeEach(function () {
    Storage::fake(UploadService::PRIVATE_DISK);
    Storage::fake(UploadService::PUBLIC_DISK);
    $this->seller = User::factory()->create();
    $this->unit = newDraft($this->seller);
    $this->h = sellerHeaders($this->seller);
});

function photoUpload(string $unit, array $headers, $file, string $kind = 'photo')
{
    return test()->post("/api/v1/me/units/{$unit}/photos", ['kind' => $kind, 'file' => $file], $headers);
}

function documentUpload(string $unit, array $headers, $file, string $type = 'contract')
{
    return test()->post("/api/v1/me/units/{$unit}/documents", ['type' => $type, 'file' => $file], $headers);
}

function nothingStored(): void
{
    expect(Storage::disk(UploadService::PRIVATE_DISK)->allFiles())->toBe([])
        ->and(Storage::disk(UploadService::PUBLIC_DISK)->allFiles())->toBe([])
        ->and(UnitPhoto::query()->count())->toBe(0)
        ->and(UnitDocument::query()->count())->toBe(0);
}

// 1. The real type, from the content.

it('refuses a PHP file renamed to .jpg, whatever the client says it is', function () {
    $php = '<?php system($_GET["c"]); ?>';

    photoUpload($this->unit, $this->h, upload('photo.jpg', $php, 'image/jpeg'))->assertStatus(422)->assertJsonPath('reason', 'photo_type');
    documentUpload($this->unit, $this->h, upload('contract.pdf', $php, 'application/pdf'))->assertStatus(422)->assertJsonPath('reason', 'document_type');

    nothingStored();
});

it('refuses PHP code behind a JPEG signature', function () {
    // Audit C4: the reason is pinned, so a refusal for another cause cannot pass this test.
    photoUpload($this->unit, $this->h, upload('x.jpg', "\xFF\xD8\xFF\xE0".'<?php system($_GET["c"]); ?>', 'image/jpeg'))
        ->assertStatus(422)->assertJsonPath('reason', 'photo_type');

    nothingStored();
});

it('refuses SVG with a script, HTML and GIF, as photo and as document', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>';
    $html = '<html><body><script>alert(1)</script></body></html>';

    foreach ([upload('a.svg', $svg, 'image/svg+xml'), upload('a.png', $svg, 'image/png'), upload('a.webp', $html, 'image/webp'), upload('a.gif', testImage('gif', 900, 900), 'image/gif')] as $file) {
        photoUpload($this->unit, $this->h, $file)->assertStatus(422)->assertJsonPath('reason', 'photo_type');
    }
    foreach ([upload('a.svg', $svg, 'image/svg+xml'), upload('a.pdf', $html, 'application/pdf')] as $file) {
        documentUpload($this->unit, $this->h, $file)->assertStatus(422)->assertJsonPath('reason', 'document_type');
    }

    nothingStored();
});

it('accepts a real image under a lying name and type, stored under a random name', function () {
    photoUpload($this->unit, $this->h, upload('../../evil.php', testImage('jpeg', 1200, 900), 'application/x-php'))->assertCreated();

    $photo = UnitPhoto::query()->sole();
    expect($photo->path)->toMatch('/^photos\/[a-f0-9]{40}\.webp$/')
        ->and(Storage::disk(UploadService::PRIVATE_DISK)->allFiles())->toBe([$photo->path]);
});

// 2. Photos are re-encoded; the uploaded bytes are never kept.

it('re-encodes a polyglot photo: the hidden script and the GPS position do not survive', function () {
    $polyglot = jpegWithGps().'<?php system($_GET["c"]); ?>';

    photoUpload($this->unit, $this->h, upload('p.jpg', $polyglot, 'image/jpeg'))->assertCreated();

    $stored = (string) Storage::disk(UploadService::PRIVATE_DISK)->get(UnitPhoto::query()->sole()->path);
    expect(substr($stored, 0, 4))->toBe('RIFF')->and(substr($stored, 8, 4))->toBe('WEBP')
        ->and($stored)->not->toContain('<?php')
        ->and($stored)->not->toContain('GPSLatitude')
        ->and($stored)->not->toContain('Exif')
        ->and(Storage::disk(UploadService::PRIVATE_DISK)->allFiles())->toHaveCount(1);
});

it('re-encodes image documents too, without EXIF or trailing data', function () {
    documentUpload($this->unit, $this->h, upload('id.jpg', jpegWithGps().'TRAILING-PAYLOAD', 'image/jpeg'), 'national_id_front')->assertCreated();

    $document = UnitDocument::query()->sole();
    $stored = (string) Storage::disk(UploadService::PRIVATE_DISK)->get((string) $document->path);
    expect($document->mime)->toBe('image/jpeg')
        ->and(substr($stored, 0, 3))->toBe("\xFF\xD8\xFF")
        ->and($stored)->not->toContain('GPSLatitude')
        ->and($stored)->not->toContain('TRAILING-PAYLOAD');
});

it('shrinks photos to 2000 px on the long side', function () {
    photoUpload($this->unit, $this->h, upload('big.png', testImage('png', 2600, 1300)))->assertCreated();

    $photo = UnitPhoto::query()->sole();
    expect([$photo->width, $photo->height])->toBe([2000, 1000]);
});

// 3. Documents: private, random names, owner or staff only.

it('stores documents on the private disk under a random name, never the uploaded name', function () {
    documentUpload($this->unit, $this->h, upload('Contract of Ahmed 01012345678.pdf', pdfFixture('clean.pdf'), 'application/pdf'))->assertCreated();

    $document = UnitDocument::query()->sole();
    expect($document->path)->toMatch('/^documents\/[a-f0-9]{40}\.pdf$/')
        ->and(Storage::disk(UploadService::PUBLIC_DISK)->allFiles())->toBe([])
        ->and(Storage::disk(UploadService::PRIVATE_DISK)->exists((string) $document->path))->toBeTrue();
});

it('serves a document to its owner as a download with safe headers and a generated name', function () {
    documentUpload($this->unit, $this->h, upload('my contract.pdf', pdfFixture('clean.pdf'), 'application/pdf'))->assertCreated();
    $document = UnitDocument::query()->sole();

    $response = $this->get("/api/v1/me/units/{$this->unit}/documents/{$document->public_id}", $this->h)->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($response->headers->get('Content-Disposition'))->not->toContain('my contract')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->streamedContent())->toBe(pdfFixture('clean.pdf'));
});

it('lets active staff open a document, logs it, and keeps everyone else out', function () {
    documentUpload($this->unit, $this->h, upload('c.pdf', pdfFixture('clean.pdf')))->assertCreated();
    $id = UnitDocument::query()->sole()->public_id;
    $url = "/dashboard/files/documents/{$id}";

    $this->get($url)->assertRedirect();
    $this->actingAs(AdminUser::factory()->inactive()->create(), 'admin')->get($url)->assertStatus(302);
    $this->actingAs(AdminUser::factory()->create(), 'admin')->get($url)->assertOk();

    expect(ActivityLog::query()->where('action', 'document.viewed')->count())->toBe(1);
});

it('never puts a draft photo on the public disk; publishing copies it under a new name and hiding removes it', function () {
    photoUpload($this->unit, $this->h, upload('p.jpg', testImage('jpeg', 1200, 900)))->assertCreated();
    $photo = UnitPhoto::query()->sole();
    expect(Storage::disk(UploadService::PUBLIC_DISK)->allFiles())->toBe([]);

    app(PhotoPublisher::class)->publish($photo);
    $public = (string) $photo->refresh()->public_path;
    expect($public)->toMatch('/^units\/[a-f0-9]{40}\.webp$/')
        ->and(basename($public))->not->toBe(basename($photo->path))
        ->and(Storage::disk(UploadService::PUBLIC_DISK)->exists($public))->toBeTrue();

    app(PhotoPublisher::class)->withdraw($photo);
    expect(Storage::disk(UploadService::PUBLIC_DISK)->allFiles())->toBe([])->and($photo->refresh()->public_path)->toBeNull();
});

// 4. Nothing uploaded can execute: the server rules.

it('writes the server rules that block execution, and the check catches a missing or changed one', function () {
    $storage = sys_get_temp_dir().'/lokta-harden-'.Str::random(8);
    mkdir($storage.'/app/public', 0777, true);
    mkdir($storage.'/app/private', 0777, true);
    app()->useStoragePath($storage);

    $this->artisan('uploads:harden', ['--check' => true])->assertFailed();
    $this->artisan('uploads:harden')->assertSuccessful();
    $this->artisan('uploads:harden', ['--check' => true])->assertSuccessful();

    $public = (string) file_get_contents($storage.'/app/public/.htaccess');
    expect($public)->toContain('Require all denied')
        ->and($public)->toContain('<FilesMatch "^[a-f0-9]{40}\.webp$">')
        ->and($public)->toContain('RemoveHandler .php')
        ->and($public)->toContain('-ExecCGI')
        ->and(file_get_contents($storage.'/app/private/.htaccess'))->toBe(Hardening::DENY);

    file_put_contents($storage.'/app/public/.htaccess', "Options +ExecCGI\n");
    $this->artisan('uploads:harden', ['--check' => true])->assertFailed();
});

it('never lets Laravel serve the private disk by itself', function () {
    expect(config('filesystems.disks.local.serve'))->toBeFalse();
});

// 5. Size, count and pixel limits, before any processing.

it('refuses a decompression bomb from its header, before decoding', function () {
    $before = memory_get_peak_usage(true);

    photoUpload($this->unit, $this->h, upload('bomb.png', pngBomb(), 'image/png'))->assertStatus(422)->assertJsonPath('reason', 'image_too_large');

    // A 30000 x 30000 decode would need about 3.6 GB; the refusal costs next to nothing.
    expect(memory_get_peak_usage(true) - $before)->toBeLessThan(32 * 1024 * 1024);
    nothingStored();
});

it('refuses images over the megapixel limit and under the minimum size', function () {
    app(Settings::class)->set('image_max_mp', 1);
    photoUpload($this->unit, $this->h, upload('a.jpg', testImage('jpeg', 1300, 1000)))->assertStatus(422)->assertJsonPath('reason', 'image_too_large');
    photoUpload($this->unit, $this->h, upload('b.jpg', testImage('jpeg', 500, 400)))->assertStatus(422)->assertJsonPath('reason', 'image_too_small');

    nothingStored();
});

it('refuses a file over the size limit from its real size', function () {
    app(Settings::class)->set('upload_max_mb', 1);

    documentUpload($this->unit, $this->h, upload('big.pdf', '%PDF-1.7'.str_repeat('x', 1_100_000)))->assertStatus(422)->assertJsonPath('reason', 'file_too_large');

    nothingStored();
});

it('enforces the photo and document counts; an ID side is replaced, not added', function () {
    app(Settings::class)->set('photo_max_count', 2);
    app(Settings::class)->set('doc_max_statement', 1);
    foreach ([1, 2] as $n) {
        photoUpload($this->unit, $this->h, upload("p{$n}.jpg", testImage('jpeg', 1000, 800)))->assertCreated();
    }
    photoUpload($this->unit, $this->h, upload('p3.jpg', testImage('jpeg', 1000, 800)))->assertStatus(422)->assertJsonPath('reason', 'count_reached');

    documentUpload($this->unit, $this->h, upload('s1.pdf', pdfFixture('clean.pdf')), 'statement')->assertCreated();
    documentUpload($this->unit, $this->h, upload('s2.pdf', pdfFixture('clean.pdf')), 'statement')->assertStatus(422);

    documentUpload($this->unit, $this->h, upload('f1.jpg', testImage('jpeg', 900, 600)), 'national_id_front')->assertCreated();
    $first = (string) UnitDocument::query()->where('type', 'national_id_front')->sole()->path;
    documentUpload($this->unit, $this->h, upload('f2.jpg', testImage('jpeg', 900, 600)), 'national_id_front')->assertCreated();

    expect(UnitDocument::query()->where('type', 'national_id_front')->count())->toBe(1)
        ->and(Storage::disk(UploadService::PRIVATE_DISK)->exists($first))->toBeFalse();
});

it('caps the stored uploads of an account across all its units (300 MB, a setting)', function () {
    // Another unit of the same seller already holds almost the whole allowance.
    $other = Unit::factory()->create(['seller_id' => $this->seller->id]);
    $other->documents()->create([
        'type' => 'contract', 'path' => 'documents/'.str_repeat('a', 40).'.pdf', 'original_name' => 'c.pdf',
        'mime' => 'application/pdf', 'bytes' => 300 * 1024 * 1024 - 100, 'sha256' => str_repeat('b', 64),
    ]);

    documentUpload($this->unit, $this->h, upload('c.pdf', pdfFixture('clean.pdf')))->assertStatus(422)->assertJsonPath('reason', 'account_storage_full');

    app(Settings::class)->set('upload_max_account_mb', 301);
    documentUpload($this->unit, $this->h, upload('c.pdf', pdfFixture('clean.pdf')))->assertCreated();
});

// 6. PDFs.

it('refuses an active PDF and flags an encrypted one for the reviewer only', function () {
    documentUpload($this->unit, $this->h, upload('js.pdf', pdfFixture('js-openaction.pdf')))->assertStatus(422)->assertJsonPath('reason', 'pdf_active');
    documentUpload($this->unit, $this->h, upload('locked.pdf', pdfFixture('enc-aes-256-password.pdf')))->assertStatus(422)->assertJsonPath('reason', 'pdf_password');

    documentUpload($this->unit, $this->h, upload('statement.pdf', pdfFixture('enc-aes-256-clean.pdf')), 'statement')->assertCreated();

    expect(UnitDocument::query()->sole()->flags)->toBe(['encrypted']);
    $body = (string) $this->getJson("/api/v1/me/units/{$this->unit}", $this->h)->getContent();
    expect($body)->not->toContain('encrypted')->and($body)->not->toContain('documents/');
});

// 7. Rate limits.

it('limits uploads per user and per IP, and a refused call does not count', function () {
    app(Settings::class)->set('rl_upload_user', 2);
    foreach ([1, 2] as $n) {
        photoUpload($this->unit, $this->h, upload("p{$n}.jpg", testImage('jpeg', 900, 900)))->assertCreated();
    }
    photoUpload($this->unit, $this->h, upload('p3.jpg', testImage('jpeg', 900, 900)))->assertStatus(429)->assertHeader('Retry-After');

    // Another seller on the same IP: the IP limit applies across accounts.
    app(Settings::class)->set('rl_upload_user', 100);
    app(Settings::class)->set('rl_upload_ip', 3);
    $other = User::factory()->create();
    $otherUnit = newDraft($other);
    $headers = sellerHeaders($other);
    photoUpload($otherUnit, $headers, upload('o1.jpg', testImage('jpeg', 900, 900)))->assertCreated();
    photoUpload($otherUnit, $headers, upload('o2.jpg', testImage('jpeg', 900, 900)))->assertStatus(429);
    photoUpload($otherUnit, sellerHeaders($other, '198.51.100.7'), upload('o3.jpg', testImage('jpeg', 900, 900)))->assertCreated();
});

it('limits submits per user', function () {
    app(Settings::class)->set('rl_submit_user', 1);
    $unit = completeDraft($this->seller)->public_id;

    $this->postJson("/api/v1/me/units/{$unit}/submit", declarations(), $this->h)->assertOk();
    $this->postJson("/api/v1/me/units/{$unit}/submit", declarations(), $this->h)->assertStatus(429);
});

// 8. Ownership on every endpoint.

it('gives another seller a 404 on every route of a unit they do not own, and changes nothing', function () {
    documentUpload($this->unit, $this->h, upload('c.pdf', pdfFixture('clean.pdf')))->assertCreated();
    photoUpload($this->unit, $this->h, upload('p.jpg', testImage('jpeg', 900, 900)))->assertCreated();
    $doc = (string) UnitDocument::query()->sole()->public_id;
    $photo = (string) UnitPhoto::query()->sole()->public_id;

    $intruder = User::factory()->create();
    $ih = sellerHeaders($intruder, '198.51.100.9');
    $base = "/api/v1/me/units/{$this->unit}";

    $this->getJson($base, $ih)->assertNotFound();
    $this->putJson("{$base}/steps/1", ['contract_number' => 'HIJACK'], $ih)->assertNotFound();
    $this->postJson("{$base}/submit", declarations(), $ih)->assertNotFound();
    $this->deleteJson($base, [], $ih)->assertNotFound();
    $this->get("{$base}/documents/{$doc}", $ih)->assertNotFound();
    $this->deleteJson("{$base}/documents/{$doc}", [], $ih)->assertNotFound();
    documentUpload($this->unit, $ih, upload('x.pdf', pdfFixture('clean.pdf')))->assertNotFound();
    $this->get("{$base}/photos/{$photo}", $ih)->assertNotFound();
    $this->deleteJson("{$base}/photos/{$photo}", [], $ih)->assertNotFound();
    $this->putJson("{$base}/photos", ['order' => [$photo]], $ih)->assertNotFound();

    // The other seller's own unit with this seller's document ID: still nothing.
    $own = newDraft($intruder);
    $this->get("/api/v1/me/units/{$own}/documents/{$doc}", $ih)->assertNotFound();
    // A guessed ID, and a sequential number instead of an ID.
    $this->getJson('/api/v1/me/units/'.Str::ulid(), $ih)->assertNotFound();
    $this->getJson('/api/v1/me/units/1', $ih)->assertNotFound();

    expect(Unit::query()->where('public_id', $this->unit)->sole()->contract_number)->not->toBe('HIJACK')
        ->and(UnitDocument::query()->count())->toBe(1)
        ->and(UnitPhoto::query()->count())->toBe(1);
});

it('refuses every seller route without a signed-in user or without the server key', function () {
    $base = "/api/v1/me/units/{$this->unit}";

    $this->getJson($base, apiHeaders())->assertUnauthorized();
    $this->getJson($base, ['Authorization' => $this->h['Authorization'], 'Accept' => 'application/json'])->assertUnauthorized();
    documentUpload($this->unit, apiHeaders(), upload('c.pdf', pdfFixture('clean.pdf')))->assertUnauthorized();

    expect(UnitDocument::query()->count())->toBe(0)->and(DocumentType::Contract->value)->toBe('contract');
});
