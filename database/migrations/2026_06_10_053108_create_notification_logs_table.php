<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id('notification_id');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->string('notification_type');
            $table->string('related_model')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('email_read_at')->nullable();
            
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
