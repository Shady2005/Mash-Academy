<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('question');
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();

            $table->index(['course_id', 'created_at']);
        });

        Schema::create('course_question_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_question_id')->constrained('course_questions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('reply');
            $table->boolean('is_instructor_reply')->default(false);
            $table->timestamps();
        });

        Schema::create('course_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
            $table->index(['course_id', 'rating']);
        });

        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('preferred_date');
            $table->string('preferred_time', 32);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('pricing_mode', 16)->default('free');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 10)->default('EGP');
            $table->string('topic', 255);
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('meeting_link', 500)->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('payment_request_id')->nullable()->constrained('payment_requests')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'preferred_date']);
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            $table->foreignId('consultation_id')
                ->nullable()
                ->after('course_package_id')
                ->constrained('consultations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consultation_id');
        });

        Schema::dropIfExists('consultations');
        Schema::dropIfExists('course_reviews');
        Schema::dropIfExists('course_question_replies');
        Schema::dropIfExists('course_questions');
    }
};
