<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_packages', function (Blueprint $table) {
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
            $table->string('instapay_qr_path')->nullable();
            $table->string('instapay_note', 255)->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'created_at']);
        });

        Schema::create('course_package_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_package_id')->constrained('course_packages')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->unique(['course_package_id', 'course_id']);
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::table('payment_requests', function (Blueprint $table) {
                $table->dropForeign(['course_id']);
            });

            Schema::table('payment_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('course_id')->nullable()->change();
                $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
                $table->foreignId('course_package_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('course_packages')
                    ->nullOnDelete();
                $table->index(['user_id', 'course_package_id', 'status'], 'pr_user_pkg_status_idx');
            });
        } else {
            Schema::table('payment_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('course_id')->nullable()->change();
                $table->foreignId('course_package_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('course_packages')
                    ->nullOnDelete();
                $table->index(['user_id', 'course_package_id', 'status'], 'pr_user_pkg_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropIndex('pr_user_pkg_status_idx');
            $table->dropConstrainedForeignId('course_package_id');
        });

        Schema::dropIfExists('course_package_course');
        Schema::dropIfExists('course_packages');
    }
};
