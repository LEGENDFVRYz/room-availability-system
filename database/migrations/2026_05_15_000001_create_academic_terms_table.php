<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_academic_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year_start'); // e.g. 2025 = SY 2025-2026
            $table->unsignedTinyInteger('semester');    // 1=1st, 2=2nd, 3=Summer
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['year_start', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_academic_terms');
    }
};
