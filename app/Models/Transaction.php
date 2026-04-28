<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            if (blank($transaction->reference)) {
                $transaction->reference = static::generateReference();
            }
        });
    }

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'type',
        'amount',
        'reference',
        'description',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    private static function generateReference(): string
    {
        do {
            $reference = 'TRX-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
