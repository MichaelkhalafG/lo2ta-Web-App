<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Rows that belong to one unit (PLAN 3.7 #9-#16): features, photos, documents, declarations, review outcomes,
| pending changes after publishing, duplicate attempts and exit reports.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_feature', function (Blueprint $table) {
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->restrictOnDelete();
            $table->primary(['unit_id', 'feature_id']);
        });

        Schema::create('unit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->default('photo')->comment('photo | floor_plan');
            $table->boolean('is_project_image')->default(false)->comment('shows the "Project image" label');
            $table->string('path')->comment('base path on the public disk; width variants derive from it');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->timestamps();
            $table->index(['unit_id', 'kind', 'sort']);
        });

        // PRIVATE: every column. Files live on the private disk and are served only through signed admin routes.
        Schema::create('unit_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->comment('contract | schedule | national_id_front | national_id_back | receipt | statement');
            $table->string('path')->nullable()->comment('null once purged');
            $table->string('original_name');
            $table->string('mime', 64);
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64)->index();
            $table->string('status', 24)->default('uploaded')->comment('uploaded | approved | needs_replacement');
            $table->string('review_note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('purge_after')->nullable()->index();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'type']);
        });

        Schema::create('unit_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind', 32)->comment('no_over | owner_not_broker | data_matches | terms_privacy');
            $table->string('text_version', 32);
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->index(['unit_id', 'kind']);
        });

        Schema::create('unit_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admin_users')->restrictOnDelete();
            $table->string('outcome', 24)->comment('changes_requested | rejected | published | published_verified');
            $table->text('reason')->nullable();
            $table->json('notes')->nullable()->comment('reason per field or document');
            $table->timestamp('created_at');
        });

        Schema::create('unit_pending_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->json('changes')->comment('field => [old, new]');
            $table->string('state', 16)->default('pending')->index()->comment('pending | approved | rejected');
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('note', 500)->nullable();
        });

        Schema::create('duplicate_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matched_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('key_kind', 16)->comment('contract | unit | file_sha256');
            $table->string('stage', 16)->comment('step1 | submit | upload');
            $table->boolean('blocked');
            $table->timestamp('created_at');
        });

        Schema::create('exit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->comment('declared | verified');
            $table->char('locale', 2);
            $table->json('inputs');
            $table->json('settings_snapshot');
            $table->json('results');
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at');
            $table->index(['unit_id', 'kind', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_reports');
        Schema::dropIfExists('duplicate_attempts');
        Schema::dropIfExists('unit_pending_changes');
        Schema::dropIfExists('unit_reviews');
        Schema::dropIfExists('unit_declarations');
        Schema::dropIfExists('unit_documents');
        Schema::dropIfExists('unit_photos');
        Schema::dropIfExists('unit_feature');
    }
};
