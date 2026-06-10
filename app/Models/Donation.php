<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donation extends Model
{
    protected $table = 'donations';
    protected $primaryKey = 'donation_id';
    public $timestamps = true;

    protected $fillable = [
        'iot_box_id',
        'user_id',
        'amount',
        'currency',
        'donation_type',
        'detected_at',
        'verified_at',
        'is_verified',
        'uv_sensor_status',
        'ir_sensor_status',
        'magnetic_sensor_status',
        'weight_sensor_reading',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'uv_sensor_status' => 'boolean',
        'ir_sensor_status' => 'boolean',
        'magnetic_sensor_status' => 'boolean',
        'amount' => 'decimal:2',
        'weight_sensor_reading' => 'decimal:3',
        'detected_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the IoT box that detected this donation
     */
    public function iotBox()
    {
        return $this->belongsTo(IotBox::class, 'iot_box_id', 'iot_id');
    }

    /**
     * Get the user associated with this donation
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
