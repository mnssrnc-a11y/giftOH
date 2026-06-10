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
        Schema::create('donations', function (Blueprint $table) {
            $table->id('donation_id');
            $table->unsignedBigInteger('iot_box_id');
            $table->foreign('iot_box_id')->references('iot_id')->on('iot_boxes')->onDelete('cascade');
            
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('PHP');
            $table->string('donation_type');
            
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_verified')->default(false);
            
            // Sensor readings
            $table->boolean('uv_sensor_status')->default(true);
            $table->boolean('ir_sensor_status')->default(true);
            $table->boolean('magnetic_sensor_status')->default(true);
            $table->decimal('weight_sensor_reading', 8, 3)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
