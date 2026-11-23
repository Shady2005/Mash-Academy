<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32);
            $table->string('account_name', 150);
            $table->string('profile_url', 500);
            $table->string('channel_id', 150)->nullable();
            $table->text('api_key')->nullable();
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->unsignedBigInteger('total_views')->default(0);
            $table->unsignedBigInteger('total_likes')->default(0);
            $table->decimal('engagement_rate', 5, 2)->nullable();
            $table->string('top_video_title', 255)->nullable();
            $table->string('top_video_url', 500)->nullable();
            $table->unsignedBigInteger('top_video_views')->default(0);
            $table->unsignedBigInteger('top_video_likes')->default(0);
            $table->dateTime('last_synced_at')->nullable();
            $table->string('sync_source', 40)->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['platform', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_accounts');
    }
};
