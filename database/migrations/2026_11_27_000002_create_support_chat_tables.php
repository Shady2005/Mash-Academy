<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_conversations')) {
            Schema::create('support_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('guest_name', 120)->nullable();
                $table->string('guest_email', 150)->nullable();
                $table->string('guest_token', 64)->nullable()->index();
                $table->string('subject', 255)->nullable();
                $table->string('status', 20)->default('open'); // open | resolved
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'last_message_at']);
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
                $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('sender_type', 20); // user | guest | staff
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamps();

                $table->index(['conversation_id', 'created_at']);
                $table->index(['sender_type', 'is_read']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};
