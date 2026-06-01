<?php

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
        Schema::create('tbl_notices', function (Blueprint $table) {
            $table->id();

            $table->string('title', 150);
            $table->text('body');

            $table->string('type', 30)->default('general');

            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('room_id')->nullable()->constrained('tbl_rooms')->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();

            $table->string('status', 20)->default('published');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'is_active', 'starts_at', 'ends_at'], 'idx_notice_visible');
            $table->index('type');
            $table->index(['source_type', 'source_id']);
            $table->index('room_id');
            $table->index('is_pinned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_notices');
    }
};
