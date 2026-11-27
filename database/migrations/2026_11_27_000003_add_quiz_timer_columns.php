<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quizzes', 'has_timer')) {
                $table->boolean('has_timer')->default(false)->after('is_required_for_next');
            }
            if (! Schema::hasColumn('quizzes', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->nullable()->after('has_timer');
            }
        });

        Schema::table('quiz_attempts', function (Blueprint $table): void {
            if (! Schema::hasColumn('quiz_attempts', 'started_at')) {
                $table->dateTime('started_at')->nullable()->after('passed');
            }
            if (! Schema::hasColumn('quiz_attempts', 'auto_submitted')) {
                $table->boolean('auto_submitted')->default(false)->after('submitted_at');
            }
            $table->dateTime('submitted_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table): void {
            if (Schema::hasColumn('quiz_attempts', 'auto_submitted')) {
                $table->dropColumn('auto_submitted');
            }
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            if (Schema::hasColumn('quizzes', 'duration_minutes')) {
                $table->dropColumn('duration_minutes');
            }
            if (Schema::hasColumn('quizzes', 'has_timer')) {
                $table->dropColumn('has_timer');
            }
        });
    }
};
