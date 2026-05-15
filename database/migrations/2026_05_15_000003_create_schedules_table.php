<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained('tbl_academic_terms')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('tbl_rooms')->cascadeOnDelete();
            $table->string('subject_code', 20);
            $table->string('subject_title', 100);
            $table->string('section', 30);
            $table->string('instructor_name', 100)->nullable();
            $table->unsignedTinyInteger('day_of_week'); // 1=Monday ... 7=Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Used for efficient overlap/conflict queries and status resolution
            $table->index(['room_id', 'academic_term_id', 'day_of_week', 'is_active'], 'idx_schedules_room_term_day_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_schedules');
    }
};
