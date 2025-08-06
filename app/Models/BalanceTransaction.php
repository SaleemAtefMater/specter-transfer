<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BalanceTransaction extends Model
{
    use HasFactory , softDeletes;

    protected $fillable = [
        'transaction_number',
        'safe_type_id',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'description'
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    public function safeType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SafeType::class);
    }
}
