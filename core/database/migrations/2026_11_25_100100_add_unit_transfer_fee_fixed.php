<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| The seller's own transfer fee as a fixed amount (Michael 2026-11-25, after the client's answer that most
| developers charge one). Step 3 takes a percentage (transfer_fee_bp) or an amount, never both; either replaces the
| default setting. Private, like transfer_fee_bp (Unit::PRIVATE).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedBigInteger('transfer_fee_fixed')->nullable()->after('transfer_fee_bp')->comment('EGP; the seller\'s own figure, or null');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('transfer_fee_fixed');
        });
    }
};
