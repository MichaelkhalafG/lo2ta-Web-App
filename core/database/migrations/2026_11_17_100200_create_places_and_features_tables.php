<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Managed lists the seller picks from (PLAN 3.2, 3.3): two-level areas, developers, compounds, and unit features.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->string('code', 4)->nullable()->unique()->comment('used in unit codes LK-{code}-{serial}; areas only, not governorates');
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug', 96)->unique();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('developers', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug', 96)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('compounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug', 96)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('group', 16)->comment('unit | building | compound | view');
            $table->string('name_ar');
            $table->string('name_en');
            $table->json('applies_to')->comment('list of main types');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
        Schema::dropIfExists('compounds');
        Schema::dropIfExists('developers');
        Schema::dropIfExists('areas');
    }
};
