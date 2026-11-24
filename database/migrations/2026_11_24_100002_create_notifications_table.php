<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('enrollments', 'expiry_notified_at')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dateTime('expiry_notified_at')->nullable()->after('expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('enrollments', 'expiry_notified_at')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropColumn('expiry_notified_at');
            });
        }

        Schema::dropIfExists('notifications');
    }
};
