<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SafeBalance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'safe_type_id',
        'current_balance',
        'initial_balance',
        'notes',
        'last_updated'
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'initial_balance' => 'decimal:2',
        'last_updated' => 'datetime'
    ];

    public function safeType()
    {
        return $this->belongsTo(SafeType::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($balance) {
            $balance->last_updated = now();
        });
    }
}
