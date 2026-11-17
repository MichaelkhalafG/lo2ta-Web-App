<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| One row per seller submission, which is also the public listing (PLAN 3.1 option A, 3.7 #8).
| PRIVATE columns (never in any public resource; tests/Feature/Api/PrivateFieldsTest.php):
|   contract_number, unit_number, contract_key, unit_key, active_contract_key, active_unit_key, seller_id,
|   developer_other / compound_other / area_other (free text typed by the seller), verified_by, withdraw_reason.
| Money: whole EGP in BIGINT UNSIGNED. Choices: short code strings (App\Enums).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            // Owner and state
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('state', 24)->default('draft')->index();
            $table->unsignedTinyInteger('step_reached')->default(1);
            $table->unsignedInteger('serial')->nullable()->unique()->comment('global, at first submit; e-mails say #0124');
            $table->string('code', 16)->nullable()->unique()->comment('LK-{area code}-{serial}, formed at publish, never changes');
            $table->string('slug', 160)->nullable()->unique();

            // Step 1: contract
            $table->foreignId('developer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('developer_other', 120)->nullable();
            $table->foreignId('compound_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('compound_other', 120)->nullable();
            $table->foreignId('area_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('area_other', 120)->nullable();
            $table->string('main_type', 16)->nullable();
            $table->string('sub_type', 32)->nullable();
            $table->string('contract_number', 64)->nullable();
            $table->string('unit_number', 64)->nullable();
            $table->unsignedSmallInteger('contract_year')->nullable();

            // Duplicate keys (PLAN 3.4 A). The active_* copies are filled only while the unit is submitted,
            // changes_requested, published or reserved; MariaDB has no partial indexes and NULLs never collide.
            $table->string('contract_key', 191)->nullable()->index();
            $table->string('unit_key', 191)->nullable()->index();
            $table->string('active_contract_key', 191)->nullable()->unique();
            $table->string('active_unit_key', 191)->nullable()->unique();

            // Step 2: unit
            $table->decimal('size_m2', 8, 2)->nullable();
            $table->decimal('garden_m2', 8, 2)->nullable();
            $table->decimal('roof_m2', 8, 2)->nullable();
            $table->string('floor', 10)->nullable()->comment('basement | ground | mezzanine | 1..40');
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->string('finishing', 24)->nullable();
            $table->string('delivery_status', 24)->nullable();
            $table->unsignedSmallInteger('delivery_year')->nullable();
            $table->unsignedTinyInteger('facade')->nullable()->comment('bit set: 1 north, 2 east, 4 south, 8 west');
            $table->text('description')->nullable();

            // Step 3: money and installments
            $table->unsignedBigInteger('total_price')->nullable();
            $table->unsignedBigInteger('paid_amount')->nullable();
            // Signed on purpose: an autosaved draft may briefly hold paid > total before the step check refuses it.
            $table->bigInteger('remaining_amount')->nullable()
                ->storedAs('CAST(total_price AS SIGNED) - CAST(paid_amount AS SIGNED)');
            $table->unsignedBigInteger('market_price_estimate')->nullable();
            $table->unsignedBigInteger('installment_amount')->nullable();
            $table->string('installment_frequency', 16)->nullable();
            $table->date('next_due_date')->nullable();
            $table->unsignedTinyInteger('years_left')->nullable();
            $table->unsignedBigInteger('annual_extra_amount')->nullable()->comment('null = no annual extra payment');
            $table->unsignedBigInteger('delivery_payment')->nullable()->comment('lump sum due at delivery, optional');
            $table->boolean('accepts_less')->default(false);
            $table->unsignedBigInteger('lowest_accepted_amount')->nullable();
            $table->boolean('has_overdue')->default(false);
            $table->unsignedSmallInteger('overdue_count')->nullable();
            $table->unsignedBigInteger('overdue_amount')->nullable();
            $table->unsignedBigInteger('maintenance_due')->nullable();

            // Flags set by the team
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->boolean('deal_tag')->default(false)->comment('the "Lokta deal" tag, set by the admin');

            // Dates
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('withdraw_reason', 500)->nullable();
            $table->timestamps();

            // Default sort (accepts less first, then newest) and the common filters
            $table->index(['state', 'accepts_less', 'published_at'], 'units_listing_sort');
            $table->index(['state', 'main_type', 'sub_type'], 'units_listing_type');
            $table->index(['state', 'area_id'], 'units_listing_area');
            $table->index(['state', 'compound_id'], 'units_listing_compound');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
