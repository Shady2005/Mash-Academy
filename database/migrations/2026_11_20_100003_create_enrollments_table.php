<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('payment_request_id')->nullable()->constrained('payment_requests')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->unsignedInteger('duration_value');
            $table->enum('duration_unit', ['days', 'months'])->default('months');
            $table->enum('status', ['active', 'expired', 'revoked'])->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'course_id', 'status', 'expires_at'], 'enrollments_access_lookup_idx');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
