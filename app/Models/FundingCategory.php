<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundingCategory extends Model
{
    protected $table = 'funding_categories_tb';
    protected $primaryKey = 'category_id';
    public $timestamps = true;

    protected $fillable = [
        'category_name',
        'description',
        'is_active',
        'weight_in_scoring',
        'required_documents',
        'max_amount_per_request',
        'approval_priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weight_in_scoring' => 'decimal:2',
        'required_documents' => 'array',
    ];

    /**
     * Get all funding requests in this category
     */
    public function fundingRequests()
    {
        return $this->hasMany(Funding::class, 'category_id', 'category_id');
    }
}
