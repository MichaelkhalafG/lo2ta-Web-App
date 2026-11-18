<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
| Phase 4 wizard (PLAN 2.2.15, Michael's upload security rules of 2026-11-18):
| - public_id (ULID) on units, unit_photos and unit_documents: the seller's routes name rows by this, never by the
|   sequential id. Ownership is still checked on every request; the ULID only means a guess finds nothing either.
| - unit_photos.path now lives on the PRIVATE disk (the re-encoded WebP). public_path is the copy on the public disk,
|   written only when the unit is published and the photo is not hidden; a draft's photos are never public.
| - unit_documents.flags: findings for the reviewer (e.g. "encrypted"), never shown publicly.
| One block per table, so the schema can be read statically (Larastan).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $t) {
            $t->char('public_id', 26)->nullable()->after('id');
        });
        Schema::table('unit_photos', function (Blueprint $t) {
            $t->char('public_id', 26)->nullable()->after('id');
            $t->string('path')->comment('re-encoded WebP on the PRIVATE disk')->change();
            $t->string('public_path')->nullable()->after('path')->comment('public copy, only while published and not hidden');
        });
        Schema::table('unit_documents', function (Blueprint $t) {
            $t->char('public_id', 26)->nullable()->after('id');
            $t->json('flags')->nullable()->after('sha256')->comment('reviewer findings, e.g. ["encrypted"]');
        });

        foreach (['units', 'unit_photos', 'unit_documents'] as $table) {
            foreach (DB::table($table)->whereNull('public_id')->pluck('id') as $id) {
                DB::table($table)->where('id', $id)->update(['public_id' => (string) Str::ulid()]);
            }
        }

        Schema::table('units', function (Blueprint $t) {
            $t->char('public_id', 26)->nullable(false)->unique()->change();
        });
        Schema::table('unit_photos', function (Blueprint $t) {
            $t->char('public_id', 26)->nullable(false)->unique()->change();
        });
        Schema::table('unit_documents', function (Blueprint $t) {
            $t->char('public_id', 26)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('unit_documents', function (Blueprint $t) {
            $t->dropUnique('unit_documents_public_id_unique');
            $t->dropColumn(['public_id', 'flags']);
        });
        Schema::table('unit_photos', function (Blueprint $t) {
            $t->dropUnique('unit_photos_public_id_unique');
            $t->dropColumn(['public_id', 'public_path']);
            $t->string('path')->comment('base path on the public disk; width variants derive from it')->change();
        });
        Schema::table('units', function (Blueprint $t) {
            $t->dropUnique('units_public_id_unique');
            $t->dropColumn('public_id');
        });
    }
};
