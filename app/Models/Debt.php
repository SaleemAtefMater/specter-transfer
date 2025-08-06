<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Debt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'debt_number',
        'creditor_name',
        'creditor_phone',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'safe_type_id',
        'status',
        'due_date',
        'notes'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'due_date' => 'date'
    ];

    public function safeType()
    {
        return $this->belongsTo(SafeType::class);
    }

    public function payments()
    {
        return $this->hasMany(DebtPayment::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($debt) {
            $debt->debt_number = 'DEBT' . date('Y') . str_pad(
                    Debt::whereYear('created_at', date('Y'))->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );
            $debt->remaining_amount = $debt->total_amount;
        });

        static::saving(function ($debt) {
            $debt->remaining_amount = $debt->total_amount - $debt->paid_amount;

            // Update status based on payment
            if ($debt->paid_amount == 0) {
                $debt->status = 'not_paid';
            } elseif ($debt->paid_amount < $debt->total_amount) {
                $debt->status = 'partially_paid';
            } elseif ($debt->paid_amount >= $debt->total_amount) {
                $debt->status = 'paid';
            }
        });
    }
}
