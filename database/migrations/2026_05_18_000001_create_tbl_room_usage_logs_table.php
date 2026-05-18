<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_room_usage_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')
                ->constrained('tbl_rooms')
                ->restrictOnDelete();

            $table->date('usage_date');

            $table->enum('source', [
                'schedule',
                'schedule_exception',
                'override',
                'manual',
            ]);

            $table->foreignId('schedule_id')
                ->nullable()
                ->constrained('tbl_schedules')
                ->nullOnDelete();

            $table->foreignId('schedule_exception_id')
                ->nullable()
                ->constrained('tbl_schedule_exceptions')
                ->nullOnDelete();

            $table->foreignId('room_override_id')
                ->nullable()
                ->constrained('tbl_room_overrides')
                ->nullOnDelete();

            $table->string('subject_code', 20)->nullable();
            $table->string('subject_title', 150)->nullable();
            $table->string('section', 30)->nullable();
            $table->string('instructor_name', 100)->nullable();

            $table->enum('status', [
                'reserved',
                'occupied',
                'completed',
                'cancelled',
                'auto_cancelled',
                'maintenance',
                'unavailable',
            ])->default('reserved');

            $table->time('expected_start')->nullable();
            $table->time('expected_end')->nullable();
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();

            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['usage_date', 'room_id'], 'idx_usage_date_room');
            $table->index(['usage_date', 'status'], 'idx_usage_date_status');
            $table->index(['source', 'schedule_id'], 'idx_usage_schedule_source');
            $table->index(['source', 'schedule_exception_id'], 'idx_usage_exception_source');

            $table->unique(['usage_date', 'source', 'schedule_id'], 'uniq_usage_schedule_day');
            $table->unique(['usage_date', 'source', 'schedule_exception_id'], 'uniq_usage_exception_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_room_usage_logs');
    }
};
