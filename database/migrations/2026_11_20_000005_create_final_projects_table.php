<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->longText('instructions');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->unsignedTinyInteger('required_completion_percentage')->default(95);
            $table->boolean('is_published')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_projects');
    }
};
