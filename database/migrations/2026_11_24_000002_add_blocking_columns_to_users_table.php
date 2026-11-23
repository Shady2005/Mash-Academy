<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->after('is_active');
            $table->text('blocked_reason')->nullable()->after('is_blocked');
            $table->dateTime('blocked_at')->nullable()->after('blocked_reason');
            $table->foreignId('blocked_by')->nullable()->after('blocked_at')->constrained('users')->nullOnDelete();

            $table->index(['role', 'is_blocked']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_blocked']);
            $table->dropConstrainedForeignId('blocked_by');
            $table->dropColumn(['is_blocked', 'blocked_reason', 'blocked_at']);
        });
    }
};
