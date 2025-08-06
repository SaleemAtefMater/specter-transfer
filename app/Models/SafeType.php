<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SafeType extends Model
{
    use HasFactory,softDeletes;

    protected $fillable = [
        'name',
        'account_number',
        'description',
        'type',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function safeBalance()
    {
        return $this->hasOne(SafeBalance::class);
    }

    public function debts()
    {
        return $this->hasMany(Debt::class);
    }

    public function debtPayments()
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function balanceTransactions()
    {
        return $this->hasMany(BalanceTransaction::class);
    }

    public function getCurrentBalanceAttribute()
    {
        return $this->safeBalance?->current_balance ?? 0;
    }
}
