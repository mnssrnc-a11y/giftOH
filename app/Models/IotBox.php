<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IotBox extends Model
{
    protected $table = 'iot_boxes';
    protected $primaryKey = 'iot_id';
    public $timestamps = true;

    protected $fillable = [
        'iot_serial_number',
        'user_id',
        'location',
        'latitude',
        'longitude',
        'status',
        'box_type',
        'installation_date',
        'next_maintenance_date',
        'uv_sensor_status',
        'ir_sensor_status',
        'magnetic_sensor_status',
        'weight_sensor_status',
        'firmware_version',
        'amount_collected_monthly',
        'amount_collected_yearly',
        'amount_total_lifetime',
        'last_sync',
        'last_maintenance',
    ];

    protected $casts = [
        'uv_sensor_status' => 'boolean',
        'ir_sensor_status' => 'boolean',
        'magnetic_sensor_status' => 'boolean',
        'weight_sensor_status' => 'boolean',
        'amount_collected_monthly' => 'decimal:2',
        'amount_collected_yearly' => 'decimal:2',
        'amount_total_lifetime' => 'decimal:2',
        'installation_date' => 'date',
        'next_maintenance_date' => 'date',
        'last_sync' => 'datetime',
        'last_maintenance' => 'datetime',
    ];

    /**
     * Get the user who owns this IoT box
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get all donations from this IoT box
     */
    public function donations()
    {
        return $this->hasMany(Donation::class, 'iot_box_id', 'iot_id');
    }

    /**
     * Check if all sensors are operational
     */
    public function allSensorsOperational()
    {
        return $this->uv_sensor_status &&
               $this->ir_sensor_status &&
               $this->magnetic_sensor_status &&
               $this->weight_sensor_status;
    }
}
