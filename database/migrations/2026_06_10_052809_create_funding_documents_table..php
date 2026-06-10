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
        Schema::create('funding_documents', function (Blueprint $table) {
            $table->id('document_id');
            $table->unsignedBigInteger('request_id');
            $table->foreign('request_id')->references('id')->on('funding_request_tb')->onDelete('cascade');
            
            $table->string('document_type');
            $table->string('file_path');
            $table->integer('file_size');
            $table->string('file_mime_type');
            
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            
            $table->string('verification_status')->default('pending');
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_documents');
    }
};
