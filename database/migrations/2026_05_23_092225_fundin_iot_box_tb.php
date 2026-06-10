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
        Schema::create('iot_boxes', function (Blueprint $table) {
            $table->id('iot_id');
            $table->string('iot_serial_number')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('status')->default('active');
            $table->string('box_type')->nullable();
            $table->date('installation_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->boolean('uv_sensor_status')->default(true);
            $table->boolean('ir_sensor_status')->default(true);
            $table->boolean('magnetic_sensor_status')->default(true);
            $table->boolean('weight_sensor_status')->default(true);
            $table->string('firmware_version')->nullable();
            $table->decimal('amount_collected_monthly', 10, 2)->default(0);
            $table->decimal('amount_collected_yearly', 10, 2)->default(0);
            $table->decimal('amount_total_lifetime', 12, 2)->default(0);
            $table->timestamp('last_sync')->nullable();
            $table->timestamp('last_maintenance')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iot_boxes');
    }
};
