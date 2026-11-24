<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('has_demo_video')->default(false)->after('instapay_note');
            $table->string('demo_video_type', 20)->nullable()->after('has_demo_video');
            $table->string('demo_video_value', 1000)->nullable()->after('demo_video_type');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->string('video_source_type', 20)->default('upload')->after('position');
            $table->string('bunny_library_id', 64)->nullable()->after('video_source_type');
            $table->string('bunny_video_id', 500)->nullable()->after('bunny_library_id');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['video_source_type', 'bunny_library_id', 'bunny_video_id']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['has_demo_video', 'demo_video_type', 'demo_video_value']);
        });
    }
};
