<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Phase 3 (PLAN 2.3): a buyer's favorites and their budget. One budget per account; matching is done by the admin
| (phase 7), there are no automatic alerts (out of scope).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['user_id', 'unit_id']);
        });

        Schema::create('buyer_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cash_available')->comment('EGP the buyer can pay now');
            $table->unsignedBigInteger('max_installment')->comment('EGP per installment the buyer is comfortable with');
            $table->string('installment_frequency', 16);
            $table->json('main_types')->comment('list of main type codes; RUN D12');
            $table->unsignedBigInteger('target_price')->nullable();
            $table->unsignedTinyInteger('min_bedrooms')->nullable();
            $table->string('delivery_pref', 24)->default('either')->comment('ready | under_construction | either');
            $table->timestamps();
        });

        Schema::create('buyer_budget_areas', function (Blueprint $table) {
            $table->foreignId('buyer_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->primary(['buyer_budget_id', 'area_id']);
        });

        Schema::create('buyer_budget_compounds', function (Blueprint $table) {
            $table->foreignId('buyer_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compound_id')->constrained()->restrictOnDelete();
            $table->primary(['buyer_budget_id', 'compound_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_budget_compounds');
        Schema::dropIfExists('buyer_budget_areas');
        Schema::dropIfExists('buyer_budgets');
        Schema::dropIfExists('favorites');
    }
};
