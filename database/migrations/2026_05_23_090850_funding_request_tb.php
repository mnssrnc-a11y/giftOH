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
        Schema::create('funding_request_tb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('org_name')->nullable();
            $table->text('contact_person')->nullable();
            $table->text('contact_email')->nullable();
            $table->text('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_id')->nullable();
            $table->text('mission')->nullable();
            $table->string('category')->nullable();
            $table->string('doc_image')->nullable();
            $table->string('id_image')->nullable();
            $table->string('financial_rprt')->nullable();
            $table->string('barangay_clr')->nullable();
            $table->decimal('amount_requested', 10, 2)->nullable();
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_request_tb');
    }
};
