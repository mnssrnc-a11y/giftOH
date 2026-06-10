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
        Schema::table('funding_request_tb', function (Blueprint $table) {
            // Drop string status column if it exists
            if (Schema::hasColumn('funding_request_tb', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('funding_request_tb', function (Blueprint $table) {
            // Add category_id foreign key
            $table->foreignId('category_id')->nullable()->constrained('funding_categories_tb', 'category_id')->onDelete('set null');
            
            // Add status_id foreign key
            $table->foreignId('status_id')->default(1)->constrained('request_status_tb', 'status_id');
            
            // Add additional fields for tracking
            $table->decimal('ai_score', 5, 2)->nullable();
            $table->json('ai_score_breakdown')->nullable();
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funding_request_tb', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['category_id']);
            $table->dropForeignKeyIfExists(['status_id']);
            $table->dropForeignKeyIfExists(['approved_by']);
            $table->dropColumnIfExists('category_id');
            $table->dropColumnIfExists('status_id');
            $table->dropColumnIfExists('ai_score');
            $table->dropColumnIfExists('ai_score_breakdown');
            $table->dropColumnIfExists('admin_notes');
            $table->dropColumnIfExists('approved_by');
        });
    }
};
