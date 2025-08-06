<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DebtPayment extends Model
{
    use HasFactory , softDeletes;

    protected $fillable = [
        'payment_number',
        'debt_id',
        'safe_type_id',
        'payment_amount',
        'payment_date',
        'notes'
    ];

    protected $casts = [
        'payment_amount' => 'decimal:2',
        'payment_date' => 'date'
    ];

    public function debt(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function safeType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SafeType::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            $payment->payment_number = 'PAY' . date('Y') . str_pad(
                    DebtPayment::whereYear('created_at', date('Y'))->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );
        });

        static::created(function ($payment) {
            // Update debt paid amount
            $debt = $payment->debt;
            $debt->paid_amount += $payment->payment_amount;
            $debt->save();

            // Deduct from safe balance
            $safeBalance = $payment->safeType->safeBalance;
            if ($safeBalance) {
                $safeBalance->current_balance -= $payment->payment_amount;
                $safeBalance->save();
            }

            // Create balance transaction record
            BalanceTransaction::create([
                'transaction_number' => 'TXN' . date('Y') . str_pad(
                        BalanceTransaction::whereYear('created_at', date('Y'))->count() + 1,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
                'safe_type_id' => $payment->safe_type_id,
                'type' => 'debt_payment',
                'amount' => $payment->payment_amount,
                'reference_type' => 'debt_payment',
                'reference_id' => $payment->id,
                'description' => "Debt payment to {$debt->creditor_name} - {$payment->payment_number}"
            ]);
        });
    }
}
