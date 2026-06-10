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
        Schema::create('funding_categories_tb', function (Blueprint $table) {
            $table->id('category_id');
            $table->string('category_name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('weight_in_scoring', 3, 2)->default(1.00);
            $table->json('required_documents')->nullable();
            $table->decimal('max_amount_per_request', 12, 2)->nullable();
            $table->integer('approval_priority')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_categories_tb');
    }
};
