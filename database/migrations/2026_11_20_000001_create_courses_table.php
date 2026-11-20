<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->string('short_description_ar', 500)->nullable();
            $table->longText('description');
            $table->longText('description_ar')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 10)->default('EGP');
            $table->string('thumbnail_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_featured')->default(false);
            $table->unsignedTinyInteger('featured_order')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'is_featured', 'featured_order']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
