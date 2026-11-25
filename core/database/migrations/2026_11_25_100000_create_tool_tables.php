<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Phase 6 (PLAN 3.6, 4.3; plan approved 2026-11-24 with J1-J12):
| - market_values: one row per researched calculator value (config/market_values.php): the date checked, the
|   client's confirmation and an internal note. The numbers stay in `settings`.
| - market_value_sources: the sources behind each value. Internal only (J10): never sent to the public site.
| - developer_policies: one developer's own published terms, overriding the market values for that developer.
|   Used by the calculator and the exit report only once the client approves it (J3).
| - report_indicators: the client's figures for the reports page (J9); the source stays internal.
| All four are dashboard-authored: never in any "drop and rebuild" list (handover 2.3).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_values', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->date('checked_on')->comment('when the sources were last read');
            $table->timestamp('confirmed_at')->nullable()->comment('the client confirmed the value; null = provisional');
            $table->foreignId('confirmed_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->text('note')->nullable()->comment('internal');
            $table->foreignId('updated_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('market_value_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_value_id')->constrained('market_values')->cascadeOnDelete();
            $table->string('ref', 8)->comment('register id, e.g. S1 (docs/research/market-values-2026-11.md)');
            $table->string('publisher', 160);
            $table->string('url', 2048)->nullable();
            $table->date('published_on')->nullable();
            $table->date('read_on');
            $table->text('figure')->comment('the figure in the source\'s own words');
            $table->string('origin', 64)->nullable()->comment('sources with the same origin count as one');
            $table->boolean('counted')->default(true)->comment('false = reference only, not counted for the range');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['market_value_id', 'sort']);
        });

        Schema::create('developer_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->unique()->constrained('developers')->cascadeOnDelete();
            // Overrides; null = the market value applies.
            $table->string('penalty_basis', 8)->nullable();
            $table->unsignedSmallInteger('penalty_min_bp')->nullable();
            $table->unsignedSmallInteger('penalty_max_bp')->nullable();
            $table->string('refund_rule', 8)->nullable();
            $table->unsignedTinyInteger('refund_years_fixed')->nullable();
            $table->unsignedSmallInteger('late_fee_bp')->nullable();
            $table->string('transfer_fee_mode', 8)->nullable();
            $table->unsignedSmallInteger('transfer_fee_bp')->nullable();
            $table->unsignedBigInteger('transfer_fee_fixed')->nullable();
            // The developer's own published terms (J1, J3).
            $table->string('source_title', 255);
            $table->string('source_url', 2048);
            $table->date('source_published_on')->nullable();
            $table->date('checked_on');
            $table->timestamp('client_approved_at')->nullable()->comment('null = not used anywhere (J3)');
            $table->foreignId('client_approved_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->text('note')->nullable()->comment('internal');
            $table->foreignId('updated_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('report_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('title_ar', 160);
            $table->string('title_en', 160);
            $table->decimal('value', 18, 2);
            $table->string('unit', 8)->comment('pct | egp | count | months | years');
            $table->string('note_ar', 300)->nullable();
            $table->string('note_en', 300)->nullable();
            $table->date('as_of');
            $table->text('source')->comment('internal (J10)');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->foreignId('updated_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_indicators');
        Schema::dropIfExists('developer_policies');
        Schema::dropIfExists('market_value_sources');
        Schema::dropIfExists('market_values');
    }
};
