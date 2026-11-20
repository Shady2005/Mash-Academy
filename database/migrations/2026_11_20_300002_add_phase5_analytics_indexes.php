<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->index(['user_id', 'completed_at'], 'lesson_progress_user_completed_idx');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->index(['user_id', 'quiz_id', 'percentage'], 'quiz_attempts_user_quiz_pct_idx');
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'assignment_submissions_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropIndex('lesson_progress_user_completed_idx');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex('quiz_attempts_user_quiz_pct_idx');
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropIndex('assignment_submissions_user_status_idx');
        });
    }
};
