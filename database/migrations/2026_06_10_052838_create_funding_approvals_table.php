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
        Schema::create('funding_approvals', function (Blueprint $table) {
            $table->id('approval_id');
            $table->unsignedBigInteger('request_id');
            $table->foreign('request_id')->references('id')->on('funding_request_tb')->onDelete('cascade');
            
            $table->unsignedBigInteger('approved_by');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->string('approval_status');
            $table->text('approval_notes')->nullable();
            
            $table->decimal('ai_score', 5, 2)->nullable();
            $table->json('ai_scoring_details')->nullable();
            
            $table->timestamp('decision_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_approvals');
    }
};
