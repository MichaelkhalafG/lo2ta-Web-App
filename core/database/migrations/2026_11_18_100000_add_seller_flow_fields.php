<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Phase 4 (PLAN 2.2.12 and 2.2.13, decided 2026-11-17):
| - units.transfer_fee_bp: the developer's transfer fee the seller typed for this unit (optional; else the developer
|   policy, else the setting). PRIVATE: the seller's cost, never in the buyer's figures.
| - units.contract_month / delivery_month: month (1-12) beside the existing years.
| - units.overdue_settlement: self | deduct, set only when the unit has overdue installments.
| - unit_photos.is_hidden (+ who, when, why): the admin hides a photo that shows a building number, a sign or a
|   document (PLAN 2.5); a hidden photo never reaches a public answer.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedTinyInteger('contract_month')->nullable()->after('contract_year');
            $table->unsignedTinyInteger('delivery_month')->nullable()->after('delivery_year');
            $table->unsignedSmallInteger('transfer_fee_bp')->nullable()->after('maintenance_due')
                ->comment('PRIVATE. Seller-entered developer transfer fee, basis points of the contract price');
            $table->string('overdue_settlement', 8)->nullable()->after('overdue_amount')->comment('self | deduct');
        });

        Schema::table('unit_photos', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('is_project_image');
            $table->string('hidden_reason', 500)->nullable()->after('is_hidden');
            $table->foreignId('hidden_by')->nullable()->after('hidden_reason')->constrained('admin_users')->nullOnDelete();
            $table->timestamp('hidden_at')->nullable()->after('hidden_by');
        });
    }

    public function down(): void
    {
        Schema::table('unit_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropColumn(['is_hidden', 'hidden_reason', 'hidden_at']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['contract_month', 'delivery_month', 'transfer_fee_bp', 'overdue_settlement']);
        });
    }
};
