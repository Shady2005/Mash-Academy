<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->text('notes')->nullable();
            $table->json('mcq_answers')->nullable();
            $table->unsignedInteger('mcq_score')->nullable();
            $table->unsignedInteger('mcq_max_score')->nullable();
            $table->decimal('mcq_percentage', 5, 2)->nullable();
            $table->boolean('mcq_passed')->nullable();
            $table->enum('status', ['pending', 'submitted', 'reviewed'])->default('submitted');
            $table->decimal('grade', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
            $table->index(['assignment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
