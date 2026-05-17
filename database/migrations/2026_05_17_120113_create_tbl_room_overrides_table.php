<?php

use App\Enums\RoomOverrideStatus;
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
        Schema::create('tbl_room_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('tbl_rooms')->restrictOnDelete();

            $table->enum('status', array_column(RoomOverrideStatus::cases(), 'value'));
            $table->text('reason')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index(['room_id', 'is_active', 'starts_at', 'ends_at'], 'idx_override_active_window');
            $table->index(['is_active', 'ends_at'],'idx_override_active_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_room_overrides');
    }
};
