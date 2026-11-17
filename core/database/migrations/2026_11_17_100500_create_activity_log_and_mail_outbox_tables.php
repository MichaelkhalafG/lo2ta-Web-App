<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| activity_log: every write action, with who, what, before and after (handover 2.3).
| mail_outbox: a row is written first, then the mail is sent in the request; the scheduler retries failures
| (handover 2.5). Append-only tables: no updated_at on the log.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 16)->comment('admin | user | system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id']);
        });

        Schema::create('mail_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->char('locale', 2);
            $table->string('template', 64);
            $table->json('payload');
            $table->string('state', 16)->default('pending')->comment('pending | sent | failed | dead');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_outbox');
        Schema::dropIfExists('activity_log');
    }
};
