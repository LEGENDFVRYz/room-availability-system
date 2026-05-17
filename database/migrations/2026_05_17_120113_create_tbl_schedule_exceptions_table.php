<?php

use App\Enums\ScheduleExceptionStatus;
use App\Enums\ScheduleExceptionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tbl_schedule_exceptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('schedule_id')->nullable()->constrained('tbl_schedules')->nullOnDelete();
            $table->foreignId('academic_term_id')->constrained('tbl_academic_terms')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('tbl_rooms')->restrictOnDelete();

            $table->date('event_date');

            $table->enum('event_type', array_column(ScheduleExceptionType::cases(), 'value'));

            $table->time('start_time');
            $table->time('end_time');

            $table->string('subject_code', 20)->nullable();
            $table->string('subject_title', 150)->nullable();
            $table->string('section', 30)->nullable();
            $table->string('instructor_name', 100)->nullable();

            $table->text('reason')->nullable();

            $table->enum('status', array_column(ScheduleExceptionStatus::cases(), 'value'))
                ->default(ScheduleExceptionStatus::Pending->value);

            $table->dateTime('auto_cancel_at')->nullable();
            $table->dateTime('claimed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexce
            $table->index(['event_date', 'room_id'], 'idx_exception_date_room');
            $table->index(['status', 'auto_cancel_at'], 'idx_exception_auto_cancel');
            $table->index(['event_date', 'academic_term_id'], 'idx_exception_term_date');
            $table->index('schedule_id', 'idx_exception_schedule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_schedule_exceptions');
    }
};
