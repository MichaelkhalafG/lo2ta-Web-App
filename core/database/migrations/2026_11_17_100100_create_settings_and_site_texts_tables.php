<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Settings: typed values whose definitions (type, limits, default) live in config/settings.php.
| Site texts: every text the client must supply, with a flag while ours is a placeholder (agreement clause 7).
| Both are dashboard-authored: never in any "drop and rebuild" list (handover 2.3).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('value');
            $table->boolean('is_provisional')->default(false)->comment('true until the value is researched or confirmed');
            $table->foreignId('updated_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('site_texts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique();
            $table->string('group', 64)->index();
            $table->text('ar')->nullable();
            $table->text('en')->nullable();
            $table->boolean('is_placeholder')->default(true)->index();
            $table->string('source', 16)->default('placeholder')->comment('placeholder | client');
            $table->string('note')->nullable()->comment('where the text is used, for the client');
            $table->foreignId('updated_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_texts');
        Schema::dropIfExists('settings');
    }
};
