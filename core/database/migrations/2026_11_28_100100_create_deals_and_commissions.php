<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Part 7.5 (PLAN 2.4, 3.6; Q16; G7, G8, G11, G18): deals with their stages, and one commission record per deal.
| - deals.open_unit_id: filled only while the deal is open, unique, so a unit never has two open deals (G11); MariaDB
|   has no partial indexes and NULLs do not collide (the same pattern as units.active_contract_key).
| - commissions: the base is the total contract price (Q3); rate and amount are frozen when the deal opens: the exact
|   amount the buyer consented to on the request, or the rate of that day when there was no request (G7). Status and
|   reference only: no invoice document, no part-payments (G8).
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('open_unit_id')->nullable()->unique()->comment('= unit_id while the deal is open (G11)');
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('unit_request_id')->nullable()->constrained('unit_requests')->nullOnDelete();
            $table->string('stage', 24)->index()->comment('opened | buyer_confirmed | seller_accepted | documents_approval | signing_payment | completed | cancelled');
            $table->foreignId('opened_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'stage']);
            $table->index(['buyer_id', 'stage']);
        });

        Schema::create('deal_stage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('from', 24)->nullable();
            $table->string('to', 24);
            $table->string('note', 500)->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('at');
        });

        Schema::create('commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('base_amount')->comment('total contract price, EGP (Q3)');
            $table->unsignedSmallInteger('rate_bp');
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('vat_bp')->default(0);
            $table->unsignedBigInteger('vat_amount')->default(0);
            $table->string('source', 16)->comment('consent | rate (G7)');
            $table->string('status', 16)->default('expected')->index()->comment('expected | due | invoiced | collected | waived');
            $table->date('invoiced_on')->nullable();
            $table->date('collected_on')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('waived_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('deal_stage_events');
        Schema::dropIfExists('deals');
    }
};
