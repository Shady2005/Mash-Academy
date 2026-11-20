<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_project_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('final_project_id')->constrained('final_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_original_name');
            $table->text('notes')->nullable();
            $table->enum('status', ['submitted', 'reviewed'])->default('submitted');
            $table->decimal('grade', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['final_project_id', 'user_id']);
            $table->index(['final_project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_project_submissions');
    }
};
