<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'user_id',
        'original_amount',
        'original_currency',
        'converted_amount',
        'converted_currency',
        'conversion_rate',
        'message',
        'entry_type',
        'source',
        'reference',
        'transaction_date',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'original_amount' => 'decimal:2',
        'converted_amount' => 'decimal:2',
        'conversion_rate' => 'decimal:6',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
