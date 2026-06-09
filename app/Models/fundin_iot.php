<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class fundin_iot extends Model
{
    protected $table = 'fundin_iot_box_tb';
    protected $primaryKey = 'iot_id';
    protected $fillable = [
        'place',
        'status',
        'amount_monthly',
        'amount_yearly',
        'amount_total'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
