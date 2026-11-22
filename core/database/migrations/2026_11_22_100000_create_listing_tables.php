<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Phase 5, listings (PLAN 2.3, 2.6, 3.6):
| - compounds: intro text in both languages, a client text (PLAN 6 row 17), flagged while it is a placeholder;
| - unit_requests: a buyer asks for a unit, with the 1% fee consent recorded (amount, rate, text version, time);
| - contact_messages and partnership_requests: the two public forms; the inbox is phase 7.
| Everything here is private: none of it is ever in a public answer. IPs are kept for abuse handling only.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compounds', function (Blueprint $table) {
            $table->text('intro_ar')->nullable()->after('name_en');
            $table->text('intro_en')->nullable()->after('intro_ar');
            $table->boolean('intro_is_placeholder')->default(true)->after('intro_en');
        });

        Schema::create('unit_requests', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->text('question')->nullable()->comment('contact details masked on save');
            $table->string('readiness', 16)->comment('now | within_3_months | looking');
            $table->unsignedBigInteger('fee_consent_amount')->comment('the 1% the buyer saw, EGP');
            $table->unsignedSmallInteger('fee_consent_bp');
            $table->string('fee_text_version', 32);
            $table->timestamp('fee_consent_at');
            $table->string('state', 16)->default('new')->comment('new | contacted | matched | closed');
            $table->string('closed_reason', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['buyer_id', 'state']);
            $table->index(['unit_id', 'state']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 20)->nullable();
            $table->text('message');
            $table->string('state', 16)->default('new')->index()->comment('new | handled');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('partnership_requests', function (Blueprint $table) {
            $table->id();
            $table->string('company', 160);
            $table->string('contact_name', 120);
            $table->string('job_title', 120)->nullable();
            $table->string('email', 190);
            $table->string('phone', 20);
            $table->unsignedSmallInteger('projects_count')->nullable();
            $table->text('message');
            $table->string('state', 16)->default('new')->index()->comment('new | handled');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_requests');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('unit_requests');
        Schema::table('compounds', function (Blueprint $table) {
            $table->dropColumn(['intro_ar', 'intro_en', 'intro_is_placeholder']);
        });
    }
};
