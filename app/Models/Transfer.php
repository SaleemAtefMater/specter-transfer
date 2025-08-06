<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transfers';

    protected $fillable = [
        'transfer_number',
        'transfer_type_id',
        'customer_name',
        'phone_number',
        'sent_amount',
        'transfer_cost',
        'customer_price',
        'receiver_net_amount',
        'status',
        'notes',
        'transfer_photo'
    ];

    protected $casts = [
        'sent_amount' => 'decimal:2',
        'transfer_cost' => 'decimal:2',
        'customer_price' => 'decimal:2',
        'receiver_net_amount' => 'decimal:2'
    ];

    public function transferType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TransferType::class);
    }

    // Calculate profit/loss
    public function getProfitAttribute(): float
    {
        $sentAmount = $this->sent_amount ?? 0;
        $transferCost = $this->transfer_cost ?? 0;
        $customerPrice = $this->customer_price ?? 0;
        if ($this->status == "delivered"){
            return ($sentAmount - $transferCost) - $customerPrice ;
        }
        return 0;
    }


    // Generate unique transfer number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            $transfer->transfer_number = 'TR' . date('Y') . str_pad(
                    Transfer::whereYear('created_at', date('Y'))->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );
        });

        // Auto-calculate receiver net amount
//        static::saving(function ($transfer) {
//            $transfer->receiver_net_amount = $transfer->sent_amount - $transfer->customer_price;
//        });

    }
}
