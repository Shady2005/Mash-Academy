<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->string('title');
            $table->enum('submission_type', ['file', 'mcq', 'both'])->default('file');
            $table->longText('instructions')->nullable();
            $table->unsignedTinyInteger('pass_mark')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['course_id', 'lesson_id']);
        });

        Schema::create('assignment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->text('question_text');
            $table->unsignedInteger('position')->default(1);
            $table->unsignedInteger('points')->default(1);
            $table->timestamps();

            $table->index(['assignment_id', 'position']);
        });

        Schema::create('assignment_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('assignment_questions')->cascadeOnDelete();
            $table->string('option_text', 500);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_options');
        Schema::dropIfExists('assignment_questions');
        Schema::dropIfExists('assignments');
    }
};
