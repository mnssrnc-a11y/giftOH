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
        Schema::create('funding_appeals', function (Blueprint $table) {
            $table->id('appeal_id');
            $table->unsignedBigInteger('original_request_id');
            $table->foreign('original_request_id')->references('id')->on('funding_request_tb')->onDelete('cascade');
            
            $table->unsignedBigInteger('resubmitted_request_id')->nullable();
            $table->foreign('resubmitted_request_id')->references('id')->on('funding_request_tb')->onDelete('set null');
            
            $table->text('reason_for_reapply')->nullable();
            $table->text('previous_rejection_reason')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_appeals');
    }
};
