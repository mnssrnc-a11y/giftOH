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
        Schema::create('beneficiary_profiles', function (Blueprint $table) {
            $table->id('beneficiary_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->string('organization_name')->nullable();
            $table->string('organization_type')->nullable();
            
            $table->string('verification_status')->default('unverified');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            
            $table->string('annual_report_url')->nullable();
            $table->string('tax_id')->nullable();
            
            $table->string('representative_name')->nullable();
            $table->string('representative_phone')->nullable();
            
            $table->text('impact_description')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiary_profiles');
    }
};
