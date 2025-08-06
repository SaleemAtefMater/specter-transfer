<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transfer_types';
    protected $fillable = [
        'name',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function transfers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Transfer::class);
    }
}
