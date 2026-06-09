<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class funding extends Model
{
    protected $table = 'funding_request_tb';
    protected $primaryKey = 'fund_id';
    protected $foreignKey = 'user_id';
    protected $fillable = [
        'title',
        'description',
        'doc_image',
        'id_image',
        'bank_statement',
        'amount_requested',
        'amount_paid',
        'status',
        'approved_at',
        'rejected_at',
        'completed_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
