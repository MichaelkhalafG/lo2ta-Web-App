<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Staff set-password links (PLAN 2.7, G13 and G14): a one-use link e-mailed to a new staff member (invite) or to one
| who forgot their password (reset), valid 60 minutes. Only the sha256 of the token is stored; the token itself
| lives only in the e-mail. Issuing a new link for someone ends their earlier ones.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_password_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('purpose', 8)->comment('invite | reset');
            $table->char('token_hash', 64)->unique()->comment('sha256 of the token; the token is only in the e-mail');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
            $table->index(['admin_user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_links');
    }
};
